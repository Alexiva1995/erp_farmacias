<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Jobs\ProcessSupplierConnectionJob;
use App\Models\ExchangeRate;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use App\Models\SupplierConnectionStatus;
use App\Models\User;
use App\Services\Email\GmailImapService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupplierEmailCatalogService
{
    public function __construct(
        protected GmailImapService $imapService
    ) {
    }

    /**
     * Sincroniza el catálogo por correo para un proveedor específico o para todos los proveedores configurados.
     * Busca el último correo con Excel del proveedor, esté leído o no.
     */
    public function syncEmailCatalogs(bool $dryRun = false, ?Supplier $targetSupplier = null): array
    {
        $email = config('mail_sync.email');
        $password = config('mail_sync.password');
        $host = config('mail_sync.host', 'imap.gmail.com');
        $port = (int) config('mail_sync.port', 993);
        $folder = config('mail_sync.folder', 'INBOX');
        $allowedExtensions = config('mail_sync.allowed_extensions', ['xlsx', 'xls', 'csv']);

        if (empty($email) || empty($password)) {
            throw new Exception("Las credenciales de Gmail (GMAIL_SYNC_EMAIL y GMAIL_SYNC_PASSWORD) no están configuradas en el archivo .env.");
        }

        $this->imapService->connect($host, $port, $email, $password);
        $this->imapService->selectFolder($folder);

        $processed = [];
        $skipped = [];
        $errors = [];

        $latestExchangeRate = ExchangeRate::orderByDesc('created_at')
            ->where('currency_code', 'BS')
            ->first();
        $rate = $latestExchangeRate ? (float) $latestExchangeRate->rate : null;
        $systemUser = User::first();
        $userId = $systemUser?->id ?? 1;

        if ($targetSupplier) {
            // Sincronizar proveedor específico
            $suppliersToProcess = [$targetSupplier];
        } else {
            // Sincronizar solo proveedores activos con conexión tipo file/email que tengan un correo configurado
            $suppliersToProcess = Supplier::where('is_active', true)
                ->whereHas('connections', function ($q) {
                    $q->whereIn('type', ['file', 'email'])
                      ->where(function ($sq) {
                          $sq->where('username', 'LIKE', '%@%')
                             ->orWhere('host', 'LIKE', '%@%');
                      });
                })
                ->get();
        }

        foreach ($suppliersToProcess as $supplier) {
            $connection = $supplier->connections()->first();
            $supplierEmail = $this->extractSupplierEmail($connection);

            if (empty($supplierEmail)) {
                $skipped[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'reason' => 'El proveedor no tiene conexión de tipo Archivo Excel con correo configurado.',
                ];
                continue;
            }

            if (!$connection || empty($connection->structure)) {
                $errors[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'error' => "El proveedor {$supplier->name} no tiene configurado el mapeo de columnas para archivos Excel.",
                ];
                continue;
            }

            try {
                // Buscar correos del remitente (estén leídos o no)
                $cleanEmail = trim($supplierEmail);
                $messages = $this->imapService->search("FROM \"{$cleanEmail}\"");

                if (empty($messages)) {
                    $skipped[] = [
                        'supplier_id' => $supplier->id,
                        'supplier_name' => $supplier->name,
                        'email' => $cleanEmail,
                        'reason' => "No se encontraron correos recibidos desde '{$cleanEmail}'.",
                    ];
                    continue;
                }

                // Ordenar del más reciente al más antiguo para encontrar el último con archivo Excel
                rsort($messages);

                $foundValidEmail = false;

                foreach ($messages as $msgNum) {
                    $emailData = $this->imapService->fetchMessage((int) $msgNum, $allowedExtensions);

                    if (!$emailData || empty($emailData['attachments'])) {
                        continue;
                    }

                    // Encontramos el último correo con Excel de este proveedor
                    $foundValidEmail = true;

                    foreach ($emailData['attachments'] as $attachment) {
                        $filename = $attachment['filename'];
                        $content = $attachment['content'];

                        if ($dryRun) {
                            $processed[] = [
                                'dry_run' => true,
                                'supplier_id' => $supplier->id,
                                'supplier_name' => $supplier->name,
                                'filename' => $filename,
                                'size' => strlen($content),
                                'subject' => $emailData['subject'],
                                'from' => $emailData['from_email'],
                                'date' => $emailData['date'],
                            ];
                            continue;
                        }

                        // Guardar archivo en disco local con ruta relativa a temp/
                        $storagePath = 'temp/' . Str::slug($supplier->name) . '_' . date('Ymd_His') . '_' . $filename;
                        Storage::disk('local')->put($storagePath, $content);

                        // Registrar estado
                        $status = SupplierConnectionStatus::create([
                            'supplier_id' => $supplier->id,
                            'user_id' => $userId,
                            'status' => 'processing',
                            'message' => 'Procesando catálogo recibido por correo...',
                        ]);

                        // Obtener la tasa de cambio oficial del día en que llegó el correo
                        $emailRate = $this->getExchangeRateForDate($emailData['date'] ?? null) ?: $rate;

                        // Despachar Job de forma síncrona para procesar los productos inmediatamente
                        ProcessSupplierConnectionJob::dispatchSync(
                            $supplier,
                            $userId,
                            $storagePath,
                            $connection->structure,
                            $emailRate,
                            $status->id
                        );

                        $processed[] = [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'filename' => $filename,
                            'storage_path' => $storagePath,
                            'status_id' => $status->id,
                            'subject' => $emailData['subject'],
                            'from' => $emailData['from_email'],
                            'date' => $emailData['date'],
                        ];
                    }

                    // Detenerse al procesar el último correo válido
                    break;
                }

                if (!$foundValidEmail) {
                    $skipped[] = [
                        'supplier_id' => $supplier->id,
                        'supplier_name' => $supplier->name,
                        'email' => $cleanEmail,
                        'reason' => "Los correos recibidos de '{$cleanEmail}' no contienen archivos Excel compatibles (.xlsx, .xls, .csv).",
                    ];
                }

            } catch (\Throwable $e) {
                $errors[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'error' => $e->getMessage(),
                ];
                Log::error("❌ [GMAIL SYNC] Error procesando proveedor {$supplier->name}: " . $e->getMessage());
            }
        }

        $this->imapService->disconnect();

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Extrae el correo electrónico configurado en la conexión del proveedor.
     */
    private function extractSupplierEmail(?SupplierConnection $connection): ?string
    {
        if (!$connection || !in_array($connection->type, ['file', 'email'])) {
            return null;
        }

        if (!empty($connection->username) && str_contains($connection->username, '@')) {
            return trim($connection->username);
        }

        if (!empty($connection->host) && str_contains($connection->host, '@')) {
            return trim($connection->host);
        }

        return null;
    }

    /**
     * Obtiene la tasa de cambio en Bs del BCV correspondiente a la fecha en que se recibió el correo.
     */
    private function getExchangeRateForDate(?string $rawDate): ?float
    {
        if (empty($rawDate)) {
            return null;
        }

        try {
            $parsedDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');

            // Buscar la tasa del día o la inmediatamente anterior más cercana
            $rateRecord = ExchangeRate::where('currency_code', 'BS')
                ->whereDate('created_at', '<=', $parsedDate)
                ->orderByDesc('created_at')
                ->first();

            if ($rateRecord) {
                return (float) $rateRecord->rate;
            }
        } catch (\Throwable $e) {
            Log::warning("No se pudo parsear la fecha del correo '{$rawDate}' para buscar tasa de cambio: " . $e->getMessage());
        }

        return null;
    }
}
