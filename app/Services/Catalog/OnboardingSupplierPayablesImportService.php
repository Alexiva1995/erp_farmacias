<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Invoice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class OnboardingSupplierPayablesImportService
{
    /**
     * Parsea el archivo de Cuentas por Pagar / Proveedores y analiza las coincidencias con el ERP.
     */
    public function parseAndAnalyze(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        $rows = $this->extractRawRows($filePath);
        $parsedSuppliers = $this->structureSuppliersAndInvoices($rows);

        return $this->correlateWithExistingSuppliers($parsedSuppliers);
    }

    /**
     * Ejecuta la persistencia de proveedores y facturas en la base de datos.
     */
    public function executeImport(array $suppliersPayload): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');

        return DB::transaction(function () use ($suppliersPayload) {
            $currentUserId = Auth::id();
            $stats = [
                'suppliers_created'          => 0,
                'suppliers_updated'          => 0,
                'invoices_created'           => 0,
                'invoices_skipped_duplicate' => 0,
                'total_amount_usd'           => 0.0,
                'total_amount_ves'           => 0.0,
            ];

            foreach ($suppliersPayload as $supplierItem) {
                $isNew = (bool) ($supplierItem['is_new'] ?? false);
                $existingId = !empty($supplierItem['existing_id']) ? (int) $supplierItem['existing_id'] : null;
                $supplierId = null;

                if ($isNew || !$existingId) {
                    // Crear nuevo proveedor
                    $supplierType = in_array($supplierItem['type'] ?? '', ['drogueria', 'externo'], true)
                        ? $supplierItem['type']
                        : 'drogueria';

                    $rifFormatted = $this->formatRifForDisplay($supplierItem['rif'] ?? '');
                    $cleanPhone = $this->sanitizePhone($supplierItem['sales_phone'] ?? null);

                    $supplier = Supplier::create([
                        'name'              => trim((string) ($supplierItem['name'] ?? 'Proveedor Desconocido')),
                        'social_reason'     => trim((string) ($supplierItem['name'] ?? 'Proveedor Desconocido')),
                        'rif'               => $rifFormatted ?: null,
                        'sales_phone'       => $cleanPhone,
                        'type'              => $supplierType,
                        'credit_days'       => 15,
                        'payment_method'    => 'transferencia',
                        'is_active'         => true,
                        'is_indexed'        => true,
                    ]);

                    $supplierId = $supplier->id;
                    $stats['suppliers_created']++;
                } else {
                    // Proveedor existente: verificar si se deben enriquecer datos
                    $supplierId = $existingId;
                    $supplier = Supplier::find($supplierId);

                    if ($supplier) {
                        $needsUpdate = false;
                        $updateData = $supplierItem['update_data'] ?? [];

                        if (!empty($updateData['rif']) && empty($supplier->rif)) {
                            $supplier->rif = $this->formatRifForDisplay($updateData['rif']);
                            $needsUpdate = true;
                        }

                        if (!empty($updateData['sales_phone']) && empty($supplier->sales_phone)) {
                            $cleanPhone = $this->sanitizePhone($updateData['sales_phone']);
                            if ($cleanPhone) {
                                $supplier->sales_phone = $cleanPhone;
                                $needsUpdate = true;
                            }
                        }

                        if ($needsUpdate) {
                            $supplier->save();
                            $stats['suppliers_updated']++;
                        }
                    }
                }

                if (!$supplierId) {
                    continue;
                }

                // Procesar facturas del proveedor
                $invoices = $supplierItem['invoices'] ?? [];
                foreach ($invoices as $inv) {
                    $invoiceNumber = trim((string) ($inv['invoice_number'] ?? ''));
                    if (empty($invoiceNumber)) {
                        continue;
                    }

                    // Verificar si ya existe la factura para este proveedor
                    $alreadyExists = Invoice::where('supplier_id', $supplierId)
                        ->where('invoice_number', $invoiceNumber)
                        ->exists();

                    if ($alreadyExists) {
                        $stats['invoices_skipped_duplicate']++;
                        continue;
                    }

                    $createdDate = !empty($inv['created_date']) ? Carbon::parse($inv['created_date'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                    $expDate = !empty($inv['exp_date']) ? Carbon::parse($inv['exp_date'])->format('Y-m-d') : Carbon::parse($createdDate)->addDays(15)->format('Y-m-d');
                    $totalAmount = (float) ($inv['total_amount'] ?? 0.0);
                    $netPayable = (float) ($inv['net_payable_amount'] ?? $totalAmount);
                    $totalUsd = (float) ($inv['total_usd'] ?? 0.0);
                    $exchangeRate = (float) ($inv['exchange_rate'] ?? 0.0);

                    // Si no viene exchange_rate pero tenemos totalAmount y totalUsd > 0
                    if ($exchangeRate <= 0 && $totalUsd > 0 && $totalAmount > 0) {
                        $exchangeRate = round($totalAmount / $totalUsd, 4);
                    }

                    Invoice::create([
                        'supplier_id'          => $supplierId,
                        'invoice_number'       => $invoiceNumber,
                        'control_number'       => null,
                        'created_invoice_date' => $createdDate,
                        'received_date'        => $createdDate,
                        'exp_date'             => $expDate,
                        'payment_date'         => null,
                        'currency'             => 'Bs',
                        'is_indexed'           => $exchangeRate > 0,
                        'exchange_rate'        => $exchangeRate > 0 ? $exchangeRate : 1.0,
                        'total_amount'         => $totalAmount,
                        'net_payable_amount'   => $netPayable,
                        'total_usd'            => $totalUsd,
                        'taxable_base'         => 0.0,
                        'exempt_amount'        => 0.0,
                        'tax_amount'           => 0.0,
                        'status'               => 'pending',
                        'status_payment'       => 0,
                        'registered_by'        => $currentUserId,
                        'uploaded_by'          => $currentUserId,
                        'loaded_by'            => $currentUserId,
                    ]);

                    $stats['invoices_created']++;
                    $stats['total_amount_usd'] += $totalUsd;
                    $stats['total_amount_ves'] += $totalAmount;
                }
            }

            $stats['total_amount_usd'] = round($stats['total_amount_usd'], 2);
            $stats['total_amount_ves'] = round($stats['total_amount_ves'], 2);

            Log::info('[OnboardingSupplierPayablesImport] Importación de proveedores y CXP finalizada', $stats);

            return $stats;
        });
    }

    /**
     * Extrae las filas de texto plano / celdas desde el archivo soportado.
     */
    protected function extractRawRows(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo de cuentas por pagar no existe en la ruta: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsvFile($filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $rowRange = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0];
            $rows[] = array_map(function ($val) {
                return $val !== null ? (string) $val : '';
            }, $rowRange);
        }

        return $rows;
    }

    /**
     * Parsea un archivo CSV preservando columnas con comas.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rows[] = array_map('strval', $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Estructura los datos crudos extrayendo bloques de proveedores y sus facturas asociadas.
     */
    protected function structureSuppliersAndInvoices(array $rows): array
    {
        $suppliers = [];
        $currentSupplier = null;

        foreach ($rows as $row) {
            // Unir celdas para detección de encabezados y depuración
            $rowString = implode(' ', array_filter($row));

            // 1. Detectar si la fila es un encabezado de proveedor
            $supplierHeader = $this->extractSupplierHeader($row, $rowString);
            if ($supplierHeader !== null) {
                if ($currentSupplier !== null) {
                    $suppliers[] = $currentSupplier;
                }
                $currentSupplier = [
                    'raw_rif'      => $supplierHeader['rif'],
                    'clean_rif'    => $this->cleanRif($supplierHeader['rif']),
                    'name'         => $supplierHeader['name'],
                    'sales_phone'  => $supplierHeader['phone'],
                    'invoices'     => [],
                    'total_usd'    => 0.0,
                    'total_amount' => 0.0,
                ];
                continue;
            }

            // 2. Si tenemos un proveedor activo, verificar si la fila es una factura
            if ($currentSupplier !== null) {
                $invoice = $this->extractInvoiceRow($row);
                if ($invoice !== null) {
                    $currentSupplier['invoices'][] = $invoice;
                    $currentSupplier['total_usd'] += $invoice['total_usd'];
                    $currentSupplier['total_amount'] += $invoice['total_amount'];
                }
            }
        }

        if ($currentSupplier !== null) {
            $suppliers[] = $currentSupplier;
        }

        return $suppliers;
    }

    /**
     * Extrae información de proveedor si la fila contiene el formato RIF - NOMBRE - TELÉFONO.
     */
    protected function extractSupplierHeader(array $row, string $rowString): ?array
    {
        // Buscar en cada celda o en la cadena completa
        foreach ($row as $cell) {
            $cellTrimmed = trim($cell);
            if (empty($cellTrimmed)) {
                continue;
            }

            // Patrón: J41223670-9 - CRIST MEDICALS, C.A - 02763460426/ 04247097428
            // o J001021744 - ZOOM INTERNATIONAL SERVICES C.A. - 0000
            // o J-407570170 - ALGO
            if (preg_match('/^([JVEGPjvegp]-?\d{7,10}(?:-\d)?)\s*-\s*([^-]+?)(?:\s*-\s*(.+))?$/u', $cellTrimmed, $matches)) {
                $rif = trim($matches[1]);
                $name = trim($matches[2]);
                $phone = isset($matches[3]) ? trim($matches[3]) : null;

                // Evitar falsos positivos con títulos de reporte
                if (stripos($name, 'Relación de Cuentas') !== false || stripos($name, 'Listado') !== false) {
                    continue;
                }

                return [
                    'rif'   => $rif,
                    'name'  => $name,
                    'phone' => $phone,
                ];
            }
        }

        return null;
    }

    /**
     * Extrae los datos de una factura a partir de una fila de la hoja de cálculo.
     */
    protected function extractInvoiceRow(array $row): ?array
    {
        // Filtrar celdas no vacías
        $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));

        if (count($nonEmpty) < 5) {
            return null;
        }

        // Buscar celda que contenga una fecha DD/MM/YYYY
        $dateIndexes = [];
        foreach ($row as $idx => $cell) {
            if ($this->isValidDate($cell)) {
                $dateIndexes[] = $idx;
            }
        }

        if (count($dateIndexes) < 1) {
            return null;
        }

        $createdDateIdx = $dateIndexes[0];
        $expDateIdx = count($dateIndexes) > 1 ? $dateIndexes[1] : $createdDateIdx;

        $createdDate = $this->parseDateString($row[$createdDateIdx]);
        $expDate = $this->parseDateString($row[$expDateIdx]);

        // Documento suele estar inmediatamente antes de la primera fecha
        $docCandidate = '';
        for ($i = $createdDateIdx - 1; $i >= 0; $i--) {
            $val = trim($row[$i] ?? '');
            if (!empty($val) && strtoupper($val) !== 'FCM' && strtoupper($val) !== 'FAC') {
                $docCandidate = $val;
                break;
            }
        }

        if (empty($docCandidate)) {
            return null;
        }

        // Extraer montos numéricos después de la fecha de vencimiento
        $numericValues = [];
        for ($i = $expDateIdx + 1; $i < count($row); $i++) {
            $val = trim($row[$i] ?? '');
            if ($val !== '' && $this->isNumericFormat($val)) {
                $numericValues[] = $this->parseNumericValue($val);
            }
        }

        if (empty($numericValues)) {
            return null;
        }

        // Estructura esperada de montos: [dias_venc (opc), debito, credito/total, saldo, saldo_usd, tasa_cambio]
        // Identificar débitos/créditos y saldo USD
        $totalAmount = 0.0;
        $netPayable = 0.0;
        $totalUsd = 0.0;
        $exchangeRate = 0.0;

        if (count($numericValues) >= 4) {
            // Si el primer valor es un número entero pequeño (días vencidos, ej -1, 99)
            $offset = (count($numericValues) >= 5 && abs($numericValues[0]) <= 365 && floor($numericValues[0]) == $numericValues[0]) ? 1 : 0;

            $credit = $numericValues[$offset + 1] ?? 0.0;
            $saldo = $numericValues[$offset + 2] ?? $credit;
            $usd = $numericValues[$offset + 3] ?? 0.0;
            $rate = $numericValues[$offset + 4] ?? 0.0;

            $totalAmount = $credit > 0 ? $credit : $saldo;
            $netPayable = $saldo > 0 ? $saldo : $totalAmount;
            $totalUsd = $usd;
            $exchangeRate = $rate;
        } else {
            $totalAmount = $numericValues[0] ?? 0.0;
            $netPayable = $totalAmount;
            $totalUsd = $numericValues[1] ?? 0.0;
            $exchangeRate = $numericValues[2] ?? 0.0;
        }

        return [
            'invoice_number'     => $docCandidate,
            'created_date'       => $createdDate,
            'exp_date'           => $expDate,
            'total_amount'       => round($totalAmount, 2),
            'net_payable_amount' => round($netPayable, 2),
            'total_usd'          => round($totalUsd, 2),
            'exchange_rate'      => round($exchangeRate, 4),
        ];
    }

    /**
     * Correlaciona los proveedores del archivo con los registros existentes en base de datos.
     */
    protected function correlateWithExistingSuppliers(array $parsedSuppliers): array
    {
        $existingSuppliers = Supplier::withoutGlobalScope('not_deleted')
            ->select(['id', 'name', 'rif', 'sales_phone', 'type', 'is_active'])
            ->get();

        $existingByCleanRif = [];
        $existingByName = [];

        foreach ($existingSuppliers as $supplier) {
            $cleanRif = $this->cleanRif($supplier->rif ?? '');
            if (!empty($cleanRif)) {
                $existingByCleanRif[$cleanRif] = $supplier;
            }

            $normalizedName = $this->normalizeText($supplier->name);
            if (!empty($normalizedName)) {
                $existingByName[$normalizedName] = $supplier;
            }
        }

        $matchedList = [];
        $newList = [];
        $totalInvoicesCount = 0;
        $totalAccumUsd = 0.0;
        $totalAccumVes = 0.0;

        foreach ($parsedSuppliers as $item) {
            $cleanRif = $item['clean_rif'];
            $normalizedName = $this->normalizeText($item['name']);
            $matchedSupplier = null;
            $matchReason = null;

            // 1. Coincidencia por RIF
            if (!empty($cleanRif) && isset($existingByCleanRif[$cleanRif])) {
                $matchedSupplier = $existingByCleanRif[$cleanRif];
                $matchReason = 'rif';
            }

            // 2. Coincidencia por Nombre si no hubo por RIF
            if (!$matchedSupplier && !empty($normalizedName)) {
                if (isset($existingByName[$normalizedName])) {
                    $matchedSupplier = $existingByName[$normalizedName];
                    $matchReason = 'name_exact';
                } else {
                    // Búsqueda difusa de nombre
                    foreach ($existingByName as $existName => $supplier) {
                        if (str_contains($existName, $normalizedName) || str_contains($normalizedName, $existName) || similar_text($existName, $normalizedName, $perc) > 0 && $perc >= 75) {
                            $matchedSupplier = $supplier;
                            $matchReason = 'name_fuzzy';
                            break;
                        }
                    }
                }
            }

            $invoicesCount = count($item['invoices']);
            $totalInvoicesCount += $invoicesCount;
            $totalAccumUsd += $item['total_usd'];
            $totalAccumVes += $item['total_amount'];

            if ($matchedSupplier) {
                // Determinar qué datos faltan o se pueden enriquecer
                $updates = [];
                if (empty($matchedSupplier->rif) && !empty($item['raw_rif'])) {
                    $updates['rif'] = $item['raw_rif'];
                }
                $cleanPhone = $this->sanitizePhone($item['sales_phone']);
                if (empty($matchedSupplier->sales_phone) && !empty($cleanPhone)) {
                    $updates['sales_phone'] = $cleanPhone;
                }

                $matchedList[] = [
                    'extracted_rif'       => $item['raw_rif'],
                    'extracted_name'      => $item['name'],
                    'extracted_phone'     => $cleanPhone,
                    'existing_id'         => $matchedSupplier->id,
                    'existing_name'       => $matchedSupplier->name,
                    'existing_rif'        => $matchedSupplier->rif,
                    'existing_phone'      => $matchedSupplier->sales_phone,
                    'existing_type'       => $matchedSupplier->type?->value ?? $matchedSupplier->type ?? 'drogueria',
                    'match_reason'        => $matchReason,
                    'updates_to_apply'    => $updates,
                    'invoices_count'      => $invoicesCount,
                    'total_usd'           => round($item['total_usd'], 2),
                    'total_amount'        => round($item['total_amount'], 2),
                    'invoices'            => $item['invoices'],
                ];
            } else {
                // Proveedor Nuevo
                $cleanPhone = $this->sanitizePhone($item['sales_phone']);
                $newList[] = [
                    'rif'            => $item['raw_rif'],
                    'clean_rif'      => $cleanRif,
                    'name'           => $item['name'],
                    'sales_phone'    => $cleanPhone,
                    'suggested_type' => $this->guessSupplierType($item['name']),
                    'invoices_count' => $invoicesCount,
                    'total_usd'      => round($item['total_usd'], 2),
                    'total_amount'   => round($item['total_amount'], 2),
                    'invoices'       => $item['invoices'],
                ];
            }
        }

        return [
            'summary' => [
                'total_suppliers_found' => count($parsedSuppliers),
                'matched_suppliers'     => count($matchedList),
                'new_suppliers'         => count($newList),
                'total_invoices'        => $totalInvoicesCount,
                'total_amount_usd'      => round($totalAccumUsd, 2),
                'total_amount_ves'      => round($totalAccumVes, 2),
            ],
            'matched_suppliers' => $matchedList,
            'new_suppliers'     => $newList,
        ];
    }

    /**
     * Limpia un RIF dejando solo letras y números en mayúsculas (ej. 'J412236709').
     */
    protected function cleanRif(string $rif): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $rif));
    }

    /**
     * Formatea un RIF estándar venezolano para guardarlo amigablemente (ej. 'J-41223670-9').
     */
    protected function formatRifForDisplay(string $rif): string
    {
        $clean = $this->cleanRif($rif);
        if (empty($clean)) {
            return '';
        }

        $prefix = substr($clean, 0, 1);
        $body = substr($clean, 1);

        if (strlen($body) >= 9) {
            $main = substr($body, 0, -1);
            $digit = substr($body, -1);
            return "{$prefix}-{$main}-{$digit}";
        }

        return "{$prefix}-{$body}";
    }

    /**
     * Normaliza un texto para comparación (minúsculas, sin acentos ni puntuaciones).
     */
    protected function normalizeText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $str = mb_strtolower(trim($text), 'UTF-8');
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', '.', ',', '-', '_', '/', '&'],
            ['a', 'e', 'i', 'o', 'u', 'n', '', '', '', '', '', 'y'],
            $str
        );

        return preg_replace('/\s+/', ' ', $str);
    }

    /**
     * Infiere si el proveedor probablemente es Droguería o Proveedor de Gastos/Externo según su nombre.
     */
    protected function guessSupplierType(string $name): string
    {
        $upper = strtoupper($name);
        if (str_contains($upper, 'DROGUERIA') || str_contains($upper, 'MEDICAL') || str_contains($upper, 'PHARMA') || str_contains($upper, 'FARMA') || str_contains($upper, 'LABORATORIO')) {
            return 'drogueria';
        }

        if (str_contains($upper, 'ZOOM') || str_contains($upper, 'EXPRESS') || str_contains($upper, 'SERVICIOS') || str_contains($upper, 'TRANSPORTE') || str_contains($upper, 'ALCALDIA') || str_contains($upper, 'CANTV')) {
            return 'externo';
        }

        return 'drogueria';
    }

    /**
     * Sanitiza un número de teléfono ignorando cadenas vacías o '0000'.
     */
    protected function sanitizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = trim(preg_replace('/[^\d\/\-\s]/', '', $phone));
        if ($cleaned === '' || $cleaned === '0000' || $cleaned === '0' || $cleaned === '00000000') {
            return null;
        }

        return $cleaned;
    }

    /**
     * Verifica si una cadena tiene formato de fecha DD/MM/YYYY o YYYY-MM-DD.
     */
    protected function isValidDate(string $str): bool
    {
        $str = trim($str);
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $str)) {
            return true;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return true;
        }
        return false;
    }

    /**
     * Convierte una cadena de fecha a formato ISO 'YYYY-MM-DD'.
     */
    protected function parseDateString(string $dateStr): string
    {
        $dateStr = trim($dateStr);
        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    /**
     * Determina si una cadena representa un valor numérico con formato es-VE / en-US.
     */
    protected function isNumericFormat(string $val): bool
    {
        $val = trim($val);
        return (bool) preg_match('/^-?\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $val) || (bool) preg_match('/^-?\d+(?:\.\d+)?$/', $val);
    }

    /**
     * Convierte cadenas numéricas venezolanas (ej. '7.221,30') o estándar a float.
     */
    protected function parseNumericValue(string $val): float
    {
        $val = trim($val);
        if (str_contains($val, ',') && str_contains($val, '.')) {
            // Formato '7.221,30'
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } elseif (str_contains($val, ',')) {
            // Formato '7221,30'
            $val = str_replace(',', '.', $val);
        }

        return (float) preg_replace('/[^\d.-]/', '', $val);
    }
}
