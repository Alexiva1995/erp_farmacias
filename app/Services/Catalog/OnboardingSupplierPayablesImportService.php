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

class OnboardingSupplierPayablesImportService
{
    /**
     * Parsea el archivo de Listado de Proveedores y/o el archivo de Cuentas por Pagar (CXP).
     */
    public function parseAndAnalyze(?string $suppliersFilePath = null, ?string $payablesFilePath = null): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        if (!$suppliersFilePath && !$payablesFilePath) {
            throw new \InvalidArgumentException('Debe proporcionar al menos un archivo de Proveedores o de Cuentas por Pagar.');
        }

        $directorySuppliers = [];
        if ($suppliersFilePath && file_exists($suppliersFilePath)) {
            $rawRows = $this->extractRawRows($suppliersFilePath);
            $directorySuppliers = $this->structureSuppliersDirectory($rawRows);
        }

        $payablesSuppliers = [];
        if ($payablesFilePath && file_exists($payablesFilePath)) {
            $rawRows = $this->extractRawRows($payablesFilePath);
            $payablesSuppliers = $this->structureSuppliersAndInvoices($rawRows);
        }

        $combinedSuppliers = $this->mergeDirectoryAndPayables($directorySuppliers, $payablesSuppliers);

        return $this->correlateWithExistingSuppliers($combinedSuppliers);
    }

    /**
     * Ejecuta la persistencia de proveedores y facturas en la base de datos.
     */
    public function executeImport(array $suppliersPayload): array
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');

        return DB::transaction(function () use ($suppliersPayload) {
            $currentUserId = Auth::id() ?: 1;
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

                $name = trim((string) ($supplierItem['name'] ?? 'Proveedor Desconocido'));
                $rifFormatted = $this->formatRifForDisplay($supplierItem['rif'] ?? '');
                $cleanPhone = $this->sanitizePhone($supplierItem['sales_phone'] ?? null);
                $cleanAddress = $this->sanitizeAddress($supplierItem['address'] ?? null);

                if ($isNew || !$existingId) {
                    $supplierType = in_array($supplierItem['type'] ?? '', ['drogueria', 'externo'], true)
                        ? $supplierItem['type']
                        : 'drogueria';

                    $supplier = Supplier::create([
                        'name'                   => $name,
                        'social_reason'          => $name,
                        'rif'                    => $rifFormatted ?: null,
                        'sales_phone'            => $cleanPhone,
                        'address'                => $cleanAddress,
                        'type'                   => $supplierType,
                        'credit_days'            => 15,
                        'dispatch_days'          => [1, 2, 3, 4, 5, 6],
                        'order_days'             => [1, 2, 3, 4, 5, 6],
                        'payment_due_type'       => 'invoice_date',
                        'payment_method'         => 'Bs',
                        'cash_payment'           => 0,
                        'charges_igtf'           => 0,
                        'min_order_amount'       => 0.00,
                        'is_active'              => true,
                        'is_indexed'             => true,
                    ]);

                    $supplierId = $supplier->id;
                    $stats['suppliers_created']++;
                } else {
                    $supplierId = $existingId;
                    $supplier = Supplier::find($supplierId);

                    if ($supplier) {
                        $needsUpdate = false;
                        $updateData = $supplierItem['update_data'] ?? [];

                        if (!empty($updateData['rif']) && empty($supplier->rif)) {
                            $supplier->rif = $this->formatRifForDisplay($updateData['rif']);
                            $needsUpdate = true;
                        }

                        if (!empty($updateData['sales_phone'])) {
                            $phone = $this->sanitizePhone($updateData['sales_phone']);
                            $existingPhone = trim((string) ($supplier->sales_phone ?? ''));
                            if ($phone && (empty($existingPhone) || strlen($phone) > strlen($existingPhone))) {
                                $supplier->sales_phone = $phone;
                                $needsUpdate = true;
                            }
                        }

                        if (!empty($updateData['address'])) {
                            $addr = $this->sanitizeAddress($updateData['address']);
                            $existingAddr = trim((string) ($supplier->address ?? ''));
                            if ($addr && (empty($existingAddr) || strlen($addr) > strlen($existingAddr) || in_array(strtoupper($existingAddr), ['LOCAL', 'S/N', 'S/D', 'GENERICO', '.'], true))) {
                                $supplier->address = $addr;
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
     * Parsea el archivo de Listado de Proveedores (directorio maestro).
     */
    protected function structureSuppliersDirectory(array $rows): array
    {
        $suppliers = [];

        foreach ($rows as $row) {
            $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
            if (count($nonEmpty) < 2) {
                continue;
            }

            $rowString = implode(' ', $nonEmpty);
            if (
                stripos($rowString, 'Listado de Proveedores') !== false ||
                stripos($rowString, 'ENSALUD') !== false ||
                stripos($rowString, 'Fecha Impresión') !== false ||
                (stripos($rowString, 'Código') !== false && stripos($rowString, 'Nombre') !== false)
            ) {
                continue;
            }

            $rawCode = trim($row[1] ?? '');
            $rawName = trim($row[4] ?? '');
            $rawFiscalId = trim($row[5] ?? '');
            $rawAddress = trim($row[6] ?? '');
            $rawPhone = trim($row[9] ?? $row[8] ?? $row[7] ?? '');

            // Fallback si las columnas están desplazadas
            if (empty($rawName) && count($nonEmpty) >= 2) {
                $rawCode = $nonEmpty[0];
                $rawName = $nonEmpty[1];
                $rawFiscalId = $nonEmpty[2] ?? $rawCode;
                $rawAddress = $nonEmpty[3] ?? '';
                $rawPhone = $nonEmpty[4] ?? '';
            }

            if (empty($rawName) || strlen($rawName) < 2 || str_starts_with($rawName, '/')) {
                continue;
            }

            // Ignorar genérico de plantilla
            if (strtoupper($rawName) === 'GENERICO' && strtoupper($rawCode) === '00') {
                continue;
            }

            $rifToClean = !empty($rawFiscalId) ? $rawFiscalId : $rawCode;
            $cleanRif = $this->cleanRif($rifToClean);

            $suppliers[] = [
                'raw_rif'      => $rifToClean,
                'clean_rif'    => $cleanRif,
                'name'         => $rawName,
                'sales_phone'  => $this->sanitizePhone($rawPhone),
                'address'      => $this->sanitizeAddress($rawAddress),
                'invoices'     => [],
                'total_usd'    => 0.0,
                'total_amount' => 0.0,
            ];
        }

        return $suppliers;
    }

    /**
     * Parsea el archivo de Cuentas por Pagar (CXP).
     */
    protected function structureSuppliersAndInvoices(array $rows): array
    {
        $suppliers = [];
        $currentSupplier = null;

        foreach ($rows as $row) {
            $rowString = implode(' ', array_filter($row));

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
                    'address'      => null,
                    'invoices'     => [],
                    'total_usd'    => 0.0,
                    'total_amount' => 0.0,
                ];
                continue;
            }

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
     * Combina los datos de Listado de Proveedores con los de Cuentas por Pagar (CXP).
     */
    protected function mergeDirectoryAndPayables(array $directorySuppliers, array $payablesSuppliers): array
    {
        $merged = [];
        $indexedByRif = [];
        $indexedByName = [];

        // 1. Cargar directorio de proveedores como base
        foreach ($directorySuppliers as $sup) {
            $cleanRif = $sup['clean_rif'];
            $normName = $this->normalizeText($sup['name']);

            $idx = count($merged);
            $merged[$idx] = $sup;

            if (!empty($cleanRif)) {
                $indexedByRif[$cleanRif] = $idx;
            }
            if (!empty($normName)) {
                $indexedByName[$normName] = $idx;
            }
        }

        // 2. Asociar facturas del archivo de Cuentas por Pagar
        foreach ($payablesSuppliers as $paySup) {
            $cleanRif = $paySup['clean_rif'];
            $normName = $this->normalizeText($paySup['name']);

            $targetIdx = null;
            if (!empty($cleanRif) && isset($indexedByRif[$cleanRif])) {
                $targetIdx = $indexedByRif[$cleanRif];
            } elseif (!empty($normName) && isset($indexedByName[$normName])) {
                $targetIdx = $indexedByName[$normName];
            } else {
                // Búsqueda difusa por nombre
                foreach ($indexedByName as $existName => $idx) {
                    $existNameStr = (string) $existName;
                    $normNameStr = (string) $normName;
                    if ($existNameStr !== '' && $normNameStr !== '') {
                        if (str_contains($existNameStr, $normNameStr) || str_contains($normNameStr, $existNameStr) || (similar_text($existNameStr, $normNameStr, $perc) > 0 && $perc >= 75)) {
                            $targetIdx = $idx;
                            break;
                        }
                    }
                }
            }

            if ($targetIdx !== null) {
                // Enriquecer proveedor con las facturas del reporte CXP
                $merged[$targetIdx]['invoices'] = array_merge($merged[$targetIdx]['invoices'], $paySup['invoices']);
                $merged[$targetIdx]['total_usd'] += $paySup['total_usd'];
                $merged[$targetIdx]['total_amount'] += $paySup['total_amount'];

                if (empty($merged[$targetIdx]['sales_phone']) && !empty($paySup['sales_phone'])) {
                    $merged[$targetIdx]['sales_phone'] = $paySup['sales_phone'];
                }
            } else {
                // No estaba en el directorio de proveedores: agregarlo como nuevo registro
                $idx = count($merged);
                $merged[$idx] = $paySup;

                if (!empty($cleanRif)) {
                    $indexedByRif[$cleanRif] = $idx;
                }
                if (!empty($normName)) {
                    $indexedByName[$normName] = $idx;
                }
            }
        }

        return array_values($merged);
    }

    /**
     * Extrae información de cabecera de proveedor desde el archivo de CXP.
     */
    protected function extractSupplierHeader(array $row, string $rowString): ?array
    {
        foreach ($row as $cell) {
            $cellTrimmed = trim($cell);
            if (empty($cellTrimmed)) {
                continue;
            }

            if (preg_match('/^([JVEGPjvegp]-?\d{7,10}(?:-\d)?)\s*-\s*([^-]+?)(?:\s*-\s*(.+))?$/u', $cellTrimmed, $matches)) {
                $rif = trim($matches[1]);
                $name = trim($matches[2]);
                $phone = isset($matches[3]) ? trim($matches[3]) : null;

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
        $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
        if (count($nonEmpty) < 5) {
            return null;
        }

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

        $totalAmount = 0.0;
        $netPayable = 0.0;
        $totalUsd = 0.0;
        $exchangeRate = 0.0;

        if (count($numericValues) >= 4) {
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
            ->select(['id', 'name', 'rif', 'sales_phone', 'address', 'type', 'is_active'])
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

            // 2. Coincidencia por Nombre
            if (!$matchedSupplier && !empty($normalizedName)) {
                if (isset($existingByName[$normalizedName])) {
                    $matchedSupplier = $existingByName[$normalizedName];
                    $matchReason = 'name_exact';
                } else {
                    foreach ($existingByName as $existName => $supplier) {
                        $existNameStr = (string) $existName;
                        $normNameStr = (string) $normalizedName;
                        if ($existNameStr !== '' && $normNameStr !== '') {
                            if (str_contains($existNameStr, $normNameStr) || str_contains($normNameStr, $existNameStr) || (similar_text($existNameStr, $normNameStr, $perc) > 0 && $perc >= 75)) {
                                $matchedSupplier = $supplier;
                                $matchReason = 'name_fuzzy';
                                break;
                            }
                        }
                    }
                }
            }

            $invoicesCount = count($item['invoices']);
            $totalInvoicesCount += $invoicesCount;
            $totalAccumUsd += $item['total_usd'];
            $totalAccumVes += $item['total_amount'];

            $cleanPhone = $this->sanitizePhone($item['sales_phone'] ?? null);
            $cleanAddress = $this->sanitizeAddress($item['address'] ?? null);

            if ($matchedSupplier) {
                $updates = [];
                if (empty($matchedSupplier->rif) && !empty($item['raw_rif'])) {
                    $updates['rif'] = $item['raw_rif'];
                }
                if (empty($matchedSupplier->sales_phone) && !empty($cleanPhone)) {
                    $updates['sales_phone'] = $cleanPhone;
                }
                $existingAddr = trim((string) ($matchedSupplier->address ?? ''));
                if (!empty($cleanAddress)) {
                    if (empty($existingAddr) || strlen($cleanAddress) > strlen($existingAddr) || in_array(strtoupper($existingAddr), ['LOCAL', 'S/N', 'S/D', 'GENERICO', '.'], true)) {
                        $updates['address'] = $cleanAddress;
                    }
                }

                $matchedList[] = [
                    'extracted_rif'       => $item['raw_rif'],
                    'extracted_name'      => $item['name'],
                    'extracted_phone'     => $cleanPhone,
                    'extracted_address'   => $cleanAddress,
                    'existing_id'         => $matchedSupplier->id,
                    'existing_name'       => $matchedSupplier->name,
                    'existing_rif'        => $matchedSupplier->rif,
                    'existing_phone'      => $matchedSupplier->sales_phone,
                    'existing_address'    => $matchedSupplier->address,
                    'existing_type'       => $matchedSupplier->type?->value ?? $matchedSupplier->type ?? 'drogueria',
                    'match_reason'        => $matchReason,
                    'updates_to_apply'    => $updates,
                    'invoices_count'      => $invoicesCount,
                    'total_usd'           => round($item['total_usd'], 2),
                    'total_amount'        => round($item['total_amount'], 2),
                    'invoices'            => $item['invoices'],
                ];
            } else {
                $newList[] = [
                    'rif'            => $item['raw_rif'],
                    'clean_rif'      => $cleanRif,
                    'name'           => $item['name'],
                    'sales_phone'    => $cleanPhone,
                    'address'        => $cleanAddress,
                    'suggested_type' => $this->guessSupplierType($item['name']),
                    'invoices_count' => $invoicesCount,
                    'total_usd'      => round($item['total_usd'], 2),
                    'total_amount'   => round($item['total_amount'], 2),
                    'invoices'       => $item['invoices'],
                ];
            }
        }

        $existingDirectory = $existingSuppliers->map(function ($s) {
            return [
                'id'          => $s->id,
                'name'        => $s->name,
                'rif'         => $s->rif,
                'sales_phone' => $s->sales_phone,
                'address'     => $s->address,
                'type'        => $s->type?->value ?? $s->type ?? 'drogueria',
            ];
        })->values()->all();

        return [
            'summary' => [
                'total_suppliers_found' => count($parsedSuppliers),
                'matched_suppliers'     => count($matchedList),
                'new_suppliers'         => count($newList),
                'total_invoices'        => $totalInvoicesCount,
                'total_amount_usd'      => round($totalAccumUsd, 2),
                'total_amount_ves'      => round($totalAccumVes, 2),
            ],
            'matched_suppliers'            => $matchedList,
            'new_suppliers'                => $newList,
            'existing_suppliers_directory' => $existingDirectory,
        ];
    }

    protected function extractRawRows(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo no existe en la ruta: {$filePath}");
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

    protected function cleanRif(mixed $rif): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($rif ?? '')));
    }

    protected function formatRifForDisplay(mixed $rif): string
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

    protected function normalizeText(mixed $text): string
    {
        if (empty($text)) {
            return '';
        }

        $str = mb_strtolower(trim((string) $text), 'UTF-8');
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', '.', ',', '-', '_', '/', '&'],
            ['a', 'e', 'i', 'o', 'u', 'n', '', '', '', '', '', 'y'],
            $str
        );

        return preg_replace('/\s+/', ' ', $str);
    }

    protected function guessSupplierType(mixed $name): string
    {
        $upper = strtoupper((string) ($name ?? ''));
        if (str_contains($upper, 'DROGUERIA') || str_contains($upper, 'MEDICAL') || str_contains($upper, 'PHARMA') || str_contains($upper, 'FARMA') || str_contains($upper, 'LABORATORIO')) {
            return 'drogueria';
        }

        if (str_contains($upper, 'ZOOM') || str_contains($upper, 'EXPRESS') || str_contains($upper, 'SERVICIOS') || str_contains($upper, 'TRANSPORTE') || str_contains($upper, 'ALCALDIA') || str_contains($upper, 'CANTV')) {
            return 'externo';
        }

        return 'drogueria';
    }

    protected function sanitizePhone(mixed $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $cleaned = trim(preg_replace('/[^\d\/\-\s]/', '', (string) $phone));
        if ($cleaned === '' || $cleaned === '0000' || $cleaned === '0' || $cleaned === '00000000' || (string) $phone === '.') {
            return null;
        }

        return $cleaned;
    }

    protected function sanitizeAddress(mixed $address): ?string
    {
        if (empty($address)) {
            return null;
        }

        $cleaned = trim((string) $address);
        if ($cleaned === '' || strtoupper($cleaned) === 'GENERICO' || strtoupper($cleaned) === 'S/N' || strtoupper($cleaned) === 'S/D' || $cleaned === '.') {
            return null;
        }

        return $cleaned;
    }

    protected function isValidDate(mixed $str): bool
    {
        $strVal = trim((string) ($str ?? ''));
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $strVal)) {
            return true;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $strVal)) {
            return true;
        }
        return false;
    }

    protected function parseDateString(mixed $dateStr): string
    {
        $dateStrVal = trim((string) ($dateStr ?? ''));
        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStrVal, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
            return Carbon::parse($dateStrVal)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    protected function isNumericFormat(mixed $val): bool
    {
        $strVal = trim((string) ($val ?? ''));
        return (bool) preg_match('/^-?\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $strVal) || (bool) preg_match('/^-?\d+(?:\.\d+)?$/', $strVal);
    }

    protected function parseNumericValue(mixed $val): float
    {
        $strVal = trim((string) ($val ?? ''));
        if (str_contains($strVal, ',') && str_contains($strVal, '.')) {
            $strVal = str_replace('.', '', $strVal);
            $strVal = str_replace(',', '.', $strVal);
        } elseif (str_contains($strVal, ',')) {
            $strVal = str_replace(',', '.', $strVal);
        }

        return (float) preg_replace('/[^\d.-]/', '', $strVal);
    }
}
