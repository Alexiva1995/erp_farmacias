<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\SupplierType;
use App\Models\A2SyncTask;
use App\Models\Category;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class A2DataImportService
{
    public function __construct(
        protected MasterCatalogClientService $masterClient
    ) {}

    /**
     * Construye la previsualización adaptada cruzando con Proveedores, Clientes, CXP y CXC.
     */
    public function buildPreview(array $extractedData, int $year = 2026): array
    {
        $suppliersRaw = $extractedData['suppliers'] ?? [];
        $payablesRaw = $extractedData['payables'] ?? [];
        $clientsRaw = $extractedData['clients'] ?? [];
        $receivablesRaw = $extractedData['receivables'] ?? [];
        $productsRaw = $extractedData['products'] ?? [];
        $operationsRaw = $extractedData['operations'] ?? [];

        // 0. Lista de proveedores registrados en el ERP para mapeo interactivo
        $existingSuppliersList = Supplier::query()
            ->select(['id', 'name', 'rif'])
            ->orderBy('name')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'rif' => $s->rif,
                ];
            })
            ->values()
            ->all();

        // 1. Homologación de Proveedores y agrupación de Facturas por Pagar (CXP)
        $suppliersPreview = $this->correlateSuppliersWithPayables($suppliersRaw, $payablesRaw);

        // 2. Homologación de Clientes y Cuentas por Cobrar (CXC)
        $clientsPreview = $this->correlateClientsWithReceivables($clientsRaw, $receivablesRaw);

        // 3. Homologación de Productos contra Master y Tenant
        $productsPreview = $this->correlateProducts($productsRaw);

        // 4. Filtrar Operaciones del año en curso
        $operationsFiltered = array_values(array_filter($operationsRaw, function ($op) use ($year) {
            $date = $op['date'] ?? '';
            return str_starts_with((string)$date, (string)$year);
        }));

        $salesRaw = $extractedData['sales'] ?? $operationsFiltered;

        $totalPayablesCount = 0;
        $totalPayablesAmountUsd = 0.0;
        $totalPayablesAmountVes = 0.0;
        foreach ($suppliersPreview['suppliers'] as $s) {
            $totalPayablesCount += count($s['invoices']);
            $totalPayablesAmountUsd += $s['total_pending_usd'];
            $totalPayablesAmountVes += $s['total_pending_amount'];
        }

        $totalReceivablesCount = 0;
        $totalReceivablesAmountUsd = 0.0;
        $totalReceivablesAmountVes = 0.0;
        foreach ($clientsPreview['clients'] as $c) {
            $totalReceivablesCount += count($c['receivables']);
            $totalReceivablesAmountUsd += $c['total_pending_usd'];
            $totalReceivablesAmountVes += $c['total_pending_amount'];
        }

        return [
            'summary' => [
                'year' => $year,
                'total_suppliers' => count($suppliersPreview['suppliers']),
                'suppliers_matched' => $suppliersPreview['matched_count'],
                'suppliers_new' => $suppliersPreview['new_count'],
                'total_payables' => $totalPayablesCount,
                'total_payables_usd' => round($totalPayablesAmountUsd, 2),
                'total_payables_ves' => round($totalPayablesAmountVes, 2),
                'total_clients' => count($clientsPreview['clients']),
                'clients_matched' => $clientsPreview['matched_count'],
                'clients_new' => $clientsPreview['new_count'],
                'total_receivables' => $totalReceivablesCount,
                'total_receivables_usd' => round($totalReceivablesAmountUsd, 2),
                'total_receivables_ves' => round($totalReceivablesAmountVes, 2),
                'total_products' => count($productsPreview['products']),
                'products_matched_master' => $productsPreview['matched_master_count'],
                'total_operations_year' => count($operationsFiltered),
                'total_sales_year' => count($salesRaw),
            ],
            'existing_suppliers' => $existingSuppliersList,
            'suppliers' => $suppliersPreview['suppliers'],
            'clients' => $clientsPreview['clients'],
            'products_sample' => array_slice($productsPreview['products'], 0, 100),
            'operations_sample' => array_slice($operationsFiltered, 0, 100),
            'sales_sample' => array_slice($salesRaw, 0, 100),
        ];
    }

    /**
     * Correlaciona proveedores de a2 asociando sus facturas pendientes de Scuentasxpagar.
     */
    protected function correlateSuppliersWithPayables(array $a2Suppliers, array $payablesRaw): array
    {
        $existing = Supplier::query()->get();
        $matchedCount = 0;
        $newCount = 0;

        // Agrupar facturas por código de proveedor normalizado
        $payablesGrouped = [];
        foreach ($payablesRaw as $inv) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($inv['supplier_code'] ?? '')));
            if (!empty($code)) {
                $payablesGrouped[$code][] = $inv;
            }
        }

        $results = [];

        foreach ($a2Suppliers as $a2s) {
            $rawRif = trim((string)($a2s['rif'] ?? ''));
            $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif));
            $name = trim((string)($a2s['name'] ?? ''));

            $matchedSupplier = null;
            $matchReason = null;

            // Cruce 1: Por RIF exacto
            if (!empty($cleanRif)) {
                $matchedSupplier = $existing->first(function ($sup) use ($cleanRif) {
                    $dbRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$sup->rif));
                    return !empty($dbRif) && $dbRif === $cleanRif;
                });
                if ($matchedSupplier) {
                    $matchReason = 'Coincidencia por RIF (' . $matchedSupplier->rif . ')';
                }
            }

            // Cruce 2: Por Nombre similar si no hubo cruce por RIF
            if (!$matchedSupplier && !empty($name)) {
                $cleanName = strtoupper($name);
                $matchedSupplier = $existing->first(function ($sup) use ($cleanName) {
                    $dbName = strtoupper($sup->name);
                    return $dbName === $cleanName ||
                           (stripos($dbName, 'NENA') !== false && stripos($cleanName, 'NENA') !== false) ||
                           (stripos($dbName, 'MAFARTA') !== false && stripos($cleanName, 'MAFARTA') !== false) ||
                           (stripos($dbName, 'DROMEGA') !== false && stripos($cleanName, 'DROMEGA') !== false) ||
                           (stripos($dbName, 'DROCERCA') !== false && stripos($cleanName, 'DROCERCA') !== false) ||
                           (stripos($dbName, 'INTERCONTINENTAL') !== false && stripos($cleanName, 'INTERCONTINENTAL') !== false);
                });
                if ($matchedSupplier) {
                    $matchReason = 'Coincidencia por Nombre (' . $matchedSupplier->name . ')';
                }
            }

            // Facturas asociadas a este proveedor
            $invoices = $payablesGrouped[$cleanRif] ?? [];
            $totalPending = 0.0;
            $totalPendingUsd = 0.0;
            foreach ($invoices as $inv) {
                $totalPending += (float)($inv['pending_amount'] ?? 0.0);
                $totalPendingUsd += (float)($inv['pending_usd'] ?? 0.0);
            }

            if ($matchedSupplier) {
                $matchedCount++;
                $results[] = [
                    'a2_rif' => $rawRif,
                    'clean_rif' => $cleanRif,
                    'a2_name' => $name,
                    'a2_phone' => $a2s['phone'] ?? '',
                    'a2_address' => $a2s['address'] ?? '',
                    'is_matched' => true,
                    'match_reason' => $matchReason,
                    'selected_supplier_id' => $matchedSupplier->id,
                    'matched_supplier_name' => $matchedSupplier->name,
                    'invoices' => $invoices,
                    'total_pending_amount' => round($totalPending, 2),
                    'total_pending_usd' => round($totalPendingUsd, 2),
                ];
            } else {
                $newCount++;
                $results[] = [
                    'a2_rif' => $rawRif,
                    'clean_rif' => $cleanRif,
                    'a2_name' => $name,
                    'a2_phone' => $a2s['phone'] ?? '',
                    'a2_address' => $a2s['address'] ?? '',
                    'is_matched' => false,
                    'match_reason' => 'Nuevo Proveedor a registrar',
                    'selected_supplier_id' => null,
                    'matched_supplier_name' => null,
                    'invoices' => $invoices,
                    'total_pending_amount' => round($totalPending, 2),
                    'total_pending_usd' => round($totalPendingUsd, 2),
                ];
            }
        }

        return [
            'suppliers' => $results,
            'matched_count' => $matchedCount,
            'new_count' => $newCount,
        ];
    }

    /**
     * Correlaciona clientes de a2 asociando sus cuentas por cobrar pendientes.
     */
    protected function correlateClientsWithReceivables(array $a2Clients, array $receivablesRaw): array
    {
        $existing = Client::query()->select(['id', 'identification', 'name', 'phone'])->get();
        $matchedCount = 0;
        $newCount = 0;

        // Agrupar cuentas por cobrar por código de cliente
        $receivablesGrouped = [];
        foreach ($receivablesRaw as $r) {
            $code = trim((string)($r['client_code'] ?? ''));
            if (!empty($code)) {
                $receivablesGrouped[$code][] = $r;
            }
        }

        $results = [];

        foreach ($a2Clients as $c) {
            $code = trim((string)($c['code'] ?? ''));
            $cleanIdent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
            $name = trim((string)($c['name'] ?? ''));

            $matchedClient = null;
            if (!empty($cleanIdent)) {
                $matchedClient = $existing->first(function ($client) use ($cleanIdent) {
                    $cIdent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$client->identification));
                    return !empty($cIdent) && $cIdent === $cleanIdent;
                });
            }

            if (!$matchedClient && !empty($name)) {
                $cleanName = strtoupper($name);
                $matchedClient = $existing->first(function ($client) use ($cleanName) {
                    return strtoupper((string)$client->name) === $cleanName;
                });
            }

            $docs = $receivablesGrouped[$code] ?? [];
            $totalPending = 0.0;
            $totalPendingUsd = 0.0;
            foreach ($docs as $d) {
                $totalPending += (float)($d['pending_amount'] ?? 0.0);
                $totalPendingUsd += (float)($d['pending_usd'] ?? 0.0);
            }

            if ($matchedClient) {
                $matchedCount++;
                $results[] = [
                    'code' => $code,
                    'identification' => $cleanIdent ?: $code,
                    'name' => $name,
                    'phone' => $c['phone'] ?? '',
                    'address' => $c['address'] ?? '',
                    'is_matched' => true,
                    'existing_id' => $matchedClient->id,
                    'existing_name' => $matchedClient->name,
                    'receivables' => $docs,
                    'total_pending_amount' => round($totalPending, 2),
                    'total_pending_usd' => round($totalPendingUsd, 2),
                ];
            } else {
                $newCount++;
                $results[] = [
                    'code' => $code,
                    'identification' => $cleanIdent ?: $code,
                    'name' => $name,
                    'phone' => $c['phone'] ?? '',
                    'address' => $c['address'] ?? '',
                    'is_matched' => false,
                    'existing_id' => null,
                    'existing_name' => null,
                    'receivables' => $docs,
                    'total_pending_amount' => round($totalPending, 2),
                    'total_pending_usd' => round($totalPendingUsd, 2),
                ];
            }
        }

        return [
            'clients' => $results,
            'matched_count' => $matchedCount,
            'new_count' => $newCount,
        ];
    }

    /**
     * Correlaciona productos contra Catálogo Maestro y Tenant.
     */
    protected function correlateProducts(array $a2Products): array
    {
        $barcodes = [];
        foreach ($a2Products as $p) {
            $code = trim((string)($p['code'] ?? ''));
            if (!empty($code)) {
                $barcodes[] = $code;
            }
        }

        $masterMap = [];
        if (!empty($barcodes)) {
            try {
                $masterMap = $this->masterClient->lookupBulk(array_values(array_unique($barcodes)));
            } catch (\Throwable $e) {
                Log::warning('Error en lookupBulk Catálogo Maestro: ' . $e->getMessage());
            }
        }

        $matchedMasterCount = 0;
        $processedProducts = [];

        foreach ($a2Products as $p) {
            $code = trim((string)($p['code'] ?? ''));
            $masterItem = $masterMap[$code] ?? null;

            if ($masterItem) {
                $matchedMasterCount++;
                $processedProducts[] = [
                    'code' => $code,
                    'name' => $masterItem['name'] ?? $p['name'],
                    'master_matched' => true,
                    'master_id' => $masterItem['id'] ?? null,
                    'laboratory' => $masterItem['laboratory_name'] ?? '',
                    'category' => $p['category'] ?? '',
                ];
            } else {
                $processedProducts[] = [
                    'code' => $code,
                    'name' => $p['name'],
                    'master_matched' => false,
                    'master_id' => null,
                    'laboratory' => '',
                    'category' => $p['category'] ?? '',
                ];
            }
        }

        return [
            'products' => $processedProducts,
            'matched_master_count' => $matchedMasterCount,
        ];
    }

    /**
     * Ejecuta la persistencia atómica de proveedores, CXP (facturas), clientes y CXC en la BD del Tenant.
     */
    public function executeImport(array $payload): array
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '900');

        return DB::transaction(function () use ($payload) {
            $currentUserId = Auth::id() ?: 1;
            $suppliersData = $payload['suppliers'] ?? [];
            $clientsData = $payload['clients'] ?? [];

            $stats = [
                'suppliers_created' => 0,
                'suppliers_linked' => 0,
                'invoices_created' => 0,
                'invoices_skipped' => 0,
                'clients_created' => 0,
                'clients_linked' => 0,
                'credits_created' => 0,
                'credits_skipped' => 0,
            ];

            // 1. Procesar Proveedores y sus Facturas CXP
            foreach ($suppliersData as $s) {
                $supplierId = !empty($s['selected_supplier_id']) ? (int)$s['selected_supplier_id'] : null;
                $supplierName = trim((string)($s['a2_name'] ?? ''));
                $rawRif = trim((string)($s['a2_rif'] ?? ''));
                $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif));

                if (!$supplierId && !empty($supplierName)) {
                    // Evitar duplicados si ya existe por RIF o Nombre exacto
                    $existingSupplier = null;
                    if (!empty($cleanRif)) {
                        $existingSupplier = Supplier::query()
                            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rif, '-', ''), '.', ''), ' ', '')) = ?", [$cleanRif])
                            ->first();
                    }
                    if (!$existingSupplier && !empty($supplierName)) {
                        $existingSupplier = Supplier::query()
                            ->where('name', $supplierName)
                            ->first();
                    }

                    if ($existingSupplier) {
                        $supplierId = $existingSupplier->id;
                        $stats['suppliers_linked']++;
                    } else {
                        $supplier = Supplier::create([
                            'name' => $supplierName,
                            'social_reason' => $supplierName,
                            'rif' => !empty($cleanRif) ? $cleanRif : null,
                            'sales_phone' => !empty($s['a2_phone']) ? trim((string)$s['a2_phone']) : null,
                            'address' => !empty($s['a2_address']) ? trim((string)$s['a2_address']) : null,
                            'type' => SupplierType::DROGUERIA,
                            'credit_days' => 15,
                            'dispatch_days' => [1, 2, 3, 4, 5, 6],
                            'order_days' => [1, 2, 3, 4, 5, 6],
                            'payment_due_type' => 'invoice_date',
                            'payment_method' => 'Bs',
                            'cash_payment' => 0,
                            'charges_igtf' => 0,
                            'min_order_amount' => 0.00,
                            'is_active' => true,
                            'is_indexed' => true,
                        ]);
                        $supplierId = $supplier->id;
                        $stats['suppliers_created']++;
                    }
                } elseif ($supplierId) {
                    $stats['suppliers_linked']++;
                }

                if (!$supplierId) {
                    continue;
                }

                // Facturas asociadas al proveedor
                $invoices = $s['invoices'] ?? [];
                foreach ($invoices as $inv) {
                    $docNum = trim((string)($inv['invoice_number'] ?? ''));
                    if (empty($docNum)) {
                        continue;
                    }

                    $exists = Invoice::where('supplier_id', $supplierId)
                        ->where('invoice_number', $docNum)
                        ->exists();

                    if ($exists) {
                        $stats['invoices_skipped']++;
                        continue;
                    }

                    $issueDate = !empty($inv['issue_date']) ? Carbon::parse($inv['issue_date'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                    $dueDate = !empty($inv['due_date']) ? Carbon::parse($inv['due_date'])->format('Y-m-d') : Carbon::parse($issueDate)->addDays(15)->format('Y-m-d');
                    $totalAmt = (float)($inv['total_amount'] ?? 0.0);
                    $pendingAmt = (float)($inv['pending_amount'] ?? $totalAmt);
                    $totalUsd = (float)($inv['total_usd'] ?? 0.0);
                    $exchangeRate = (float)($inv['exchange_rate'] ?? 1.0);

                    Invoice::create([
                        'supplier_id' => $supplierId,
                        'invoice_number' => $docNum,
                        'control_number' => !empty($inv['control_number']) ? trim((string)$inv['control_number']) : null,
                        'created_invoice_date' => $issueDate,
                        'received_date' => $issueDate,
                        'exp_date' => $dueDate,
                        'payment_date' => $dueDate,
                        'currency' => 'Bs',
                        'is_indexed' => $exchangeRate > 1.0,
                        'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
                        'total_amount' => $totalAmt,
                        'net_payable_amount' => $pendingAmt,
                        'total_usd' => $totalUsd,
                        'taxable_base' => 0.0,
                        'exempt_amount' => 0.0,
                        'tax_amount' => 0.0,
                        'status' => 'ordered',
                        'status_payment' => 0,
                        'registered_by' => $currentUserId,
                        'uploaded_by' => $currentUserId,
                        'loaded_by' => $currentUserId,
                    ]);

                    $stats['invoices_created']++;
                }
            }

            // 2. Procesar Clientes y Cuentas por Cobrar (CXC en Credit)
            foreach ($clientsData as $c) {
                $clientId = !empty($c['existing_id']) ? (int)$c['existing_id'] : null;
                $cleanIdent = trim((string)($c['identification'] ?? ''));
                $clientName = trim((string)($c['name'] ?? 'Cliente a2'));

                if (!$clientId && !empty($cleanIdent)) {
                    $existingClient = Client::where('identification', $cleanIdent)->first();
                    if ($existingClient) {
                        $clientId = $existingClient->id;
                        $stats['clients_linked']++;
                    } else {
                        $identType = 'V-';
                        if (str_starts_with($cleanIdent, 'J') || str_starts_with($cleanIdent, 'G')) {
                            $identType = substr($cleanIdent, 0, 1) . '-';
                        }
                        $client = Client::create([
                            'identification_type' => $identType,
                            'identification' => $cleanIdent,
                            'name' => $clientName,
                            'phone' => !empty($c['phone']) ? trim((string)$c['phone']) : null,
                            'address' => !empty($c['address']) ? trim((string)$c['address']) : null,
                            'client_type' => Client::CLIENT_TYPE_NUEVO,
                            'status' => 1,
                            'user_id' => $currentUserId,
                            'balance' => 0.0,
                            'is_spe' => false,
                        ]);
                        $clientId = $client->id;
                        $stats['clients_created']++;
                    }
                } elseif ($clientId) {
                    $stats['clients_linked']++;
                }

                if (!$clientId) {
                    continue;
                }

                // Cuentas por cobrar asociadas al cliente
                $receivables = $c['receivables'] ?? [];
                $addedBalance = 0.0;

                foreach ($receivables as $rcv) {
                    $docNum = trim((string)($rcv['invoice_number'] ?? ''));
                    if (empty($docNum)) {
                        continue;
                    }

                    $exists = Credit::where('client_id', $clientId)
                        ->where('invoice_number', $docNum)
                        ->exists();

                    if ($exists) {
                        $stats['credits_skipped']++;
                        continue;
                    }

                    $issueDate = !empty($rcv['issue_date']) ? Carbon::parse($rcv['issue_date'])->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                    $dueDate = !empty($rcv['due_date']) ? Carbon::parse($rcv['due_date'])->format('Y-m-d') : Carbon::parse($issueDate)->addDays(15)->format('Y-m-d');
                    $totalAmt = (float)($rcv['total_amount'] ?? 0.0);
                    $pendingAmt = (float)($rcv['pending_amount'] ?? $totalAmt);

                    Credit::create([
                        'client_id' => $clientId,
                        'invoice_number' => $docNum,
                        'credit_amount' => $totalAmt,
                        'pending_amount' => $pendingAmt,
                        'credit_date' => $issueDate,
                        'next_due_date' => $dueDate,
                        'status' => 'pending',
                        'credit_type' => 'standard',
                        'exchange_rate_issue' => 1.0,
                        'is_indexed' => false,
                    ]);

                    $stats['credits_created']++;
                    $addedBalance += $pendingAmt;
                }

                if ($addedBalance > 0) {
                    Client::where('id', $clientId)->increment('balance', $addedBalance);
                }
            }

            return $stats;
        });
    }
}
