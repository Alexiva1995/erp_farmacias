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

                    $currentDateOnly = null;
                    if (!empty($emailData['date'])) {
                        try {
                            $currentDateOnly = \Carbon\Carbon::parse($emailData['date'])->format('Y-m-d');
                        } catch (\Throwable $e) {
                            $currentDateOnly = null;
                        }
                    }

                    if ($latestEmailDate === null) {
                        $latestEmailDate = $currentDateOnly;
                    }

                    // Si ya establecimos la fecha más reciente y este correo es de un día anterior, detener búsqueda
                    if ($latestEmailDate !== null && $currentDateOnly !== null && $currentDateOnly !== $latestEmailDate) {
                        break;
                    }

                    $collectedEmails[] = $emailData;

                    // Si no tenemos fecha para comparar, recolectar hasta 3 correos recientes
                    if (count($collectedEmails) >= 5) {
                        break;
                    }
                }

                if (empty($collectedEmails)) {
                    $skipped[] = [
                        'supplier_id' => $supplier->id,
                        'supplier_name' => $supplier->name,
                        'email' => $cleanEmail,
                        'reason' => "Los correos recibidos de '{$cleanEmail}' no contienen archivos Excel compatibles (.xlsx, .xls, .csv).",
                    ];
                    continue;
                }

                $primaryStructure = $connection->structure ?? [];
                $secondaryStructure = (!empty($connection->secondary_structure) && is_array($connection->secondary_structure))
                    ? $connection->secondary_structure
                    : null;
                $tertiaryStructure = (!empty($connection->tertiary_structure) && is_array($connection->tertiary_structure))
                    ? $connection->tertiary_structure
                    : null;

                $structures = [
                    'primary' => $primaryStructure,
                    'secondary' => $secondaryStructure,
                    'tertiary' => $tertiaryStructure,
                ];

                $fileItems = [];
                $globalAttachmentIndex = 0;

                foreach ($collectedEmails as $emailData) {
                    foreach ($emailData['attachments'] as $attachment) {
                        $filename = $attachment['filename'];
                        $content = $attachment['content'];
                        $subject = $emailData['subject'] ?? '';

                        // Resolver qué formato le corresponde (Formato 1, Formato 2 o Formato 3)
                        $formatResolution = $this->resolveStructureForFile($filename, $subject, $globalAttachmentIndex, $structures);
                        $chosenMap = $formatResolution['structure'];
                        $formatName = $formatResolution['name'];

                        if ($dryRun) {
                            $processed[] = [
                                'dry_run' => true,
                                'supplier_id' => $supplier->id,
                                'supplier_name' => $supplier->name,
                                'filename' => $filename,
                                'size' => strlen($content),
                                'subject' => $subject,
                                'from' => $emailData['from_email'],
                                'date' => $emailData['date'],
                                'format_used' => $formatName,
                            ];
                            $globalAttachmentIndex++;
                            continue;
                        }

                        // Guardar archivo en disco local con ruta relativa a temp/
                        $storagePath = 'temp/' . Str::slug($supplier->name) . '_' . date('Ymd_His') . '_' . $globalAttachmentIndex . '_' . $filename;
                        Storage::disk('local')->put($storagePath, $content);

                        $fileItems[] = [
                            'path' => $storagePath,
                            'column_map' => $chosenMap,
                            'filename' => $filename,
                            'format_used' => $formatName,
                            'subject' => $subject,
                            'from' => $emailData['from_email'],
                            'date' => $emailData['date'],
                        ];

                        $globalAttachmentIndex++;
                    }
                }

                if ($dryRun) {
                    continue;
                }

                if (!empty($fileItems)) {
                    // Registrar estado único para el lote completo de catálogos
                    $status = SupplierConnectionStatus::create([
                        'supplier_id' => $supplier->id,
                        'user_id' => $userId,
                        'status' => 'processing',
                        'message' => 'Procesando ' . count($fileItems) . ' archivo(s) de catálogo recibidos por correo...',
                    ]);

                    // Obtener la tasa oficial del día de los correos recibidos
                    $firstEmailDate = $collectedEmails[0]['date'] ?? null;
                    $emailRate = $this->getExchangeRateForDate($firstEmailDate) ?: $rate;

                    // Despachar Job de forma síncrona combinando todos los archivos con sus respectivos formatos
                    ProcessSupplierConnectionJob::dispatchSync(
                        $supplier,
                        $userId,
                        $fileItems,
                        $primaryStructure,
                        $emailRate,
                        $status->id
                    );

                    foreach ($fileItems as $fItem) {
                        $processed[] = [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'filename' => $fItem['filename'],
                            'storage_path' => $fItem['path'],
                            'status_id' => $status->id,
                            'subject' => $fItem['subject'],
                            'from' => $fItem['from'],
                            'date' => $fItem['date'],
                            'format_used' => $fItem['format_used'],
                        ];
                    }
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

    /**
     * Resuelve cuál formato (Formato 1, Formato 2 o Formato 3) corresponde al archivo y correo dado.
     */
    private function resolveStructureForFile(string $filename, string $subject, int $index, array $structures): array
    {
        $primary = $structures['primary'] ?? [];
        $secondary = $structures['secondary'] ?? null;
        $tertiary = $structures['tertiary'] ?? null;

        $targetText = strtoupper(Str::ascii($filename . ' ' . $subject));

        // 1. Coincidencia con Formato 3 (si está configurado)
        if (!empty($tertiary) && is_array($tertiary)) {
            $keywordTertiary = !empty($tertiary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $tertiary['file_keyword']))) : null;
            if ($keywordTertiary && str_contains($targetText, $keywordTertiary)) {
                return ['name' => 'Formato 3 (' . $keywordTertiary . ')', 'structure' => $tertiary];
            }
            // Si el nombre contiene explícitamente "GENIAL" y no hay keyword configurada
            if (!$keywordTertiary && str_contains($targetText, 'GENIAL')) {
                return ['name' => 'Formato 3 (Genial)', 'structure' => $tertiary];
            }
        }

        // 2. Coincidencia con Formato 2 (si está configurado)
        if (!empty($secondary) && is_array($secondary)) {
            $keywordSecondary = !empty($secondary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $secondary['file_keyword']))) : null;
            if ($keywordSecondary && str_contains($targetText, $keywordSecondary)) {
                return ['name' => 'Formato 2 (' . $keywordSecondary . ')', 'structure' => $secondary];
            }
        }

        // 3. Coincidencia con Formato 1
        $keywordPrimary = !empty($primary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $primary['file_keyword']))) : null;
        if ($keywordPrimary && str_contains($targetText, $keywordPrimary)) {
            return ['name' => 'Formato 1 (' . $keywordPrimary . ')', 'structure' => $primary];
        }

        // 4. Asignación por orden de archivo / índice si no hubo coincidencia de palabras clave
        if ($index === 2 && !empty($tertiary)) {
            return ['name' => 'Formato 3', 'structure' => $tertiary];
        }

        if ($index === 1 && !empty($secondary)) {
            return ['name' => 'Formato 2', 'structure' => $secondary];
        }

        return ['name' => 'Formato 1', 'structure' => $primary];
    }
}

