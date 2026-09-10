<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Jobs\ProcessSupplierConnectionJob;
use App\Exports\SupplierImport;
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
use Maatwebsite\Excel\Facades\Excel;

class SupplierEmailCatalogService
{
    public function __construct(
        protected GmailImapService $imapService,
        protected SupplierQueryService $queryService,
    ) {
    }

    /**
     * Sincroniza el catálogo por correo para un proveedor específico o para todos los proveedores configurados.
     * Busca el último correo con Excel del proveedor, esté leído o no.
     */
    public function syncEmailCatalogs(bool $dryRun = false, ?Supplier $targetSupplier = null): array
    {
        @ini_set('memory_limit', '512M');

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

                // Ordenar del más reciente al más antiguo para encontrar los correos del día
                rsort($messages);

                $collectedEmails = [];
                $latestEmailDate = null;

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

                // Recopilar todos los archivos adjuntos brutos de los correos recibidos
                $rawAttachments = [];
                foreach ($collectedEmails as $emailData) {
                    foreach ($emailData['attachments'] as $attachment) {
                        $rawAttachments[] = [
                            'filename' => $attachment['filename'],
                            'content' => $attachment['content'],
                            'subject' => $emailData['subject'] ?? '',
                            'from' => $emailData['from_email'],
                            'date' => $emailData['date'],
                        ];
                    }
                }

                // Resolver los formatos en lote asegurando que los archivos sin palabra clave tomen el formato sobrante (Formato 1)
                $resolvedFormats = $this->resolveBatchStructures($rawAttachments, $structures);

                $fileItems = [];
                foreach ($rawAttachments as $index => $item) {
                    $filename = $item['filename'];
                    $content = $item['content'];
                    $formatInfo = $resolvedFormats[$index];
                    $chosenMap = $formatInfo['structure'];
                    $formatName = $formatInfo['name'];

                    if ($dryRun) {
                        $processed[] = [
                            'dry_run' => true,
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'filename' => $filename,
                            'size' => strlen($content),
                            'subject' => $item['subject'],
                            'from' => $item['from'],
                            'date' => $item['date'],
                            'format_used' => $formatName,
                        ];
                        continue;
                    }

                    // Guardar archivo en disco local con ruta relativa a temp/
                    $storagePath = 'temp/' . Str::slug($supplier->name) . '_' . date('Ymd_His') . '_' . $index . '_' . $filename;
                    Storage::disk('local')->put($storagePath, $content);

                    $fileItems[] = [
                        'path' => $storagePath,
                        'column_map' => $chosenMap,
                        'filename' => $filename,
                        'format_used' => $formatName,
                        'subject' => $item['subject'],
                        'from' => $item['from'],
                        'date' => $item['date'],
                    ];
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

                    $allProducts = [];

                    foreach ($fileItems as &$fItem) {
                        $singlePath = $fItem['path'];
                        $map = $fItem['column_map'];

                        if (!Storage::disk('local')->exists($singlePath)) {
                            $fItem['products_count'] = 0;
                            continue;
                        }

                        $absolutePath = Storage::disk('local')->path($singlePath);

                        try {
                            $nameCol = !empty($map["name"]) ? (string)$map["name"] : 'B';
                            $startRow = !empty($map["start_row"]) ? (int)$map["start_row"] : 1;

                            $import = new SupplierImport(
                                supplierId: (int) $supplier->id,
                                startRow: $startRow,
                                codSupplierCol: !empty($map["cod_supplier"]) ? $map["cod_supplier"] : null,
                                nameCol: $nameCol,
                                barcodeCol: !empty($map["barcode_match"]) ? $map["barcode_match"] : null,
                                qtyCol: !empty($map["quantity"]) ? $map["quantity"] : null,
                                costBsCol: !empty($map["unit_cost"]) ? $map["unit_cost"] : null,
                                costUsdCol: !empty($map["unit_cost_usd"]) ? $map["unit_cost_usd"] : null,
                                activeIngredientCol: !empty($map["active_ingredient"]) ? $map["active_ingredient"] : null,
                                expirationCol: !empty($map["expiration"]) ? $map["expiration"] : null,
                                currencyCol: $emailRate ?? (!empty($map["currency"]) ? (float)$map["currency"] : null),
                            );

                            Excel::import($import, $absolutePath);

                            $rows = $import->getRows();
                            $fItem['products_count'] = $rows->count();

                            if ($rows->isNotEmpty()) {
                                $allProducts = array_merge($allProducts, $rows->toArray());
                            }
                        } catch (\Throwable $e) {
                            $fItem['products_count'] = 0;
                            $fItem['error'] = $e->getMessage();
                            Log::error("Error importando archivo {$fItem['filename']}: " . $e->getMessage());
                        }

                        Storage::disk('local')->delete($singlePath);
                    }
                    unset($fItem);

                    if (empty($allProducts)) {
                        $skipped[] = [
                            'supplier_id' => $supplier->id,
                            'supplier_name' => $supplier->name,
                            'reason' => "No se pudieron extraer productos válidos de los archivos adjuntos. Se conserva el catálogo previo.",
                        ];
                        continue;
                    }

                    // Guardar todos los productos combinados en la base de datos (limpieza previa única)
                    $this->queryService->storeSupplierConnectionData($supplier, [
                        'products' => $allProducts,
                        'invoices' => [],
                    ]);

                    $this->queryService->addDiscountsToProducts($supplier);

                    \Illuminate\Support\Facades\DB::table('supplier_connections')
                        ->where('supplier_id', $supplier->id)
                        ->update(['last_connection' => now()->toDateString()]);

                    $totalCount = count($allProducts);

                    $status->update([
                        'status' => 'completed',
                        'message' => "Catálogo sincronizado exitosamente ({$totalCount} productos importados)",
                        'count_product' => $totalCount,
                        'count_invoice' => 0,
                    ]);

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
                            'products_count' => $fItem['products_count'] ?? 0,
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
     * Resuelve los formatos correspondientes para todo el lote de archivos adjuntos en 2 fases.
     */
    private function resolveBatchStructures(array $rawAttachments, array $structures): array
    {
        $primary = $structures['primary'] ?? [];
        $secondary = $structures['secondary'] ?? null;
        $tertiary = $structures['tertiary'] ?? null;

        $results = array_fill(0, count($rawAttachments), null);
        $usedFormats = [
            'primary' => false,
            'secondary' => false,
            'tertiary' => false,
        ];

        // FASE 1: Asignación por coincidencia explícita de palabra clave
        foreach ($rawAttachments as $i => $item) {
            $filename = $item['filename'];
            $subject = $item['subject'] ?? '';
            $targetText = strtoupper(Str::ascii($filename . ' ' . $subject));

            // 1.1 Coincidencia con Formato 3
            if (!empty($tertiary) && is_array($tertiary) && !$usedFormats['tertiary']) {
                $keywordTertiary = !empty($tertiary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $tertiary['file_keyword']))) : null;
                if ($keywordTertiary && str_contains($targetText, $keywordTertiary)) {
                    $results[$i] = ['name' => 'Formato 3 (' . $keywordTertiary . ')', 'structure' => $tertiary];
                    $usedFormats['tertiary'] = true;
                    continue;
                }
                if (!$keywordTertiary && str_contains($targetText, 'GENIAL')) {
                    $results[$i] = ['name' => 'Formato 3 (GENIAL)', 'structure' => $tertiary];
                    $usedFormats['tertiary'] = true;
                    continue;
                }
            }

            // 1.2 Coincidencia con Formato 2
            if (!empty($secondary) && is_array($secondary) && !$usedFormats['secondary']) {
                $keywordSecondary = !empty($secondary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $secondary['file_keyword']))) : null;
                if ($keywordSecondary && str_contains($targetText, $keywordSecondary)) {
                    $results[$i] = ['name' => 'Formato 2 (' . $keywordSecondary . ')', 'structure' => $secondary];
                    $usedFormats['secondary'] = true;
                    continue;
                }
            }

            // 1.3 Coincidencia con Formato 1
            if (!empty($primary) && is_array($primary) && !$usedFormats['primary']) {
                $keywordPrimary = !empty($primary['file_keyword']) ? strtoupper(Str::ascii(trim((string) $primary['file_keyword']))) : null;
                if ($keywordPrimary && str_contains($targetText, $keywordPrimary)) {
                    $results[$i] = ['name' => 'Formato 1 (' . $keywordPrimary . ')', 'structure' => $primary];
                    $usedFormats['primary'] = true;
                    continue;
                }
            }
        }

        // FASE 2: Asignación de los archivos restantes a los formatos no utilizados (sobrantes)
        foreach ($rawAttachments as $i => $item) {
            if ($results[$i] !== null) {
                continue;
            }

            // El archivo sobrante (sin palabra clave específica) toma prioritariamente el Formato 1 (Principal)
            if (!$usedFormats['primary']) {
                $results[$i] = ['name' => 'Formato 1 (Principal)', 'structure' => $primary];
                $usedFormats['primary'] = true;
            } elseif (!$usedFormats['secondary'] && !empty($secondary)) {
                $results[$i] = ['name' => 'Formato 2 (Secundario)', 'structure' => $secondary];
                $usedFormats['secondary'] = true;
            } elseif (!$usedFormats['tertiary'] && !empty($tertiary)) {
                $results[$i] = ['name' => 'Formato 3 (Genial)', 'structure' => $tertiary];
                $usedFormats['tertiary'] = true;
            } else {
                $results[$i] = ['name' => 'Formato 1 (Principal)', 'structure' => $primary];
            }
        }

        return $results;
    }
}

