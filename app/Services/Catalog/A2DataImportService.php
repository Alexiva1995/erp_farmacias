<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\SupplierType;
use App\Models\A2SyncTask;
use App\Models\Category;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDetail;
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

        $salesRaw = !empty($extractedData['sales'])
            ? array_values(array_filter($extractedData['sales'], fn($op) => str_starts_with((string)($op['date'] ?? ''), (string)$year)))
            : array_values(array_filter($operationsFiltered, fn($op) => ($op['op_type'] ?? 0) === 2817));

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
            'task_id' => $extractedData['task_id'] ?? null,
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
        @set_time_limit(900);
        @ignore_user_abort(true);

        return DB::transaction(function () use ($payload) {
            $currentUserId = Auth::id() ?: 1;
            $section = $payload['section'] ?? null;

            $shouldProcessProducts = ($section === 'products_lots' || empty($section));
            $shouldProcessSuppliers = ($section === 'suppliers_payables' || empty($section));
            $shouldProcessClients = ($section === 'clients' || $section === 'clients_receivables' || empty($section));

            $suppliersData = $shouldProcessSuppliers ? ($payload['suppliers'] ?? []) : [];
            $clientsData = $shouldProcessClients ? ($payload['clients'] ?? []) : [];
            $productsData = $shouldProcessProducts ? ($payload['products'] ?? []) : [];

            // Si vino un task_id y aplica productos, recuperar el listado completo extraído en la tarea
            $taskId = !empty($payload['task_id']) ? (int)$payload['task_id'] : null;
            if ($taskId && $shouldProcessProducts) {
                $task = A2SyncTask::find($taskId);
                if ($task && !empty($task->extracted_data['products'])) {
                    $productsData = $task->extracted_data['products'];
                }
            }

            $stats = [
                'suppliers_created' => 0,
                'suppliers_linked' => 0,
                'invoices_created' => 0,
                'invoices_skipped' => 0,
                'clients_created' => 0,
                'clients_linked' => 0,
                'credits_created' => 0,
                'credits_skipped' => 0,
                'products_created' => 0,
                'products_updated' => 0,
                'products_matched_master' => 0,
                'operations_count' => 0,
                'sales_count' => 0,
            ];

            $shouldProcessSales = ($section === 'sales' || $section === 'operations' || empty($section));
            if ($taskId && $shouldProcessSales) {
                $task = $task ?? A2SyncTask::find($taskId);
                if ($task && !empty($task->extracted_data['operations'])) {
                    $stats['operations_count'] = count($task->extracted_data['operations']);
                    $stats['sales_count'] = count(array_filter($task->extracted_data['operations'], fn($op) => ($op['op_type'] ?? 0) === 2817));
                }
            }

            // 0. Procesar Productos de a2 (Sinventario.dat)
            if ($shouldProcessProducts && !empty($productsData)) {
                $barcodes = [];
                foreach ($productsData as $p) {
                    $c = trim((string)($p['code'] ?? ''));
                    if ($c !== '') {
                        $barcodes[] = $c;
                    }
                }

                $masterMap = [];
                if (!empty($barcodes)) {
                    try {
                        $masterMap = $this->masterClient->lookupBulk(array_values(array_unique($barcodes)));
                    } catch (\Throwable $e) {
                        Log::warning('Error en lookupBulk Catálogo Maestro al importar: ' . $e->getMessage());
                    }
                }

                $existingProducts = Product::withoutGlobalScope('not_deleted')
                    ->withTrashed()
                    ->whereIn('barcode', $barcodes)
                    ->get()
                    ->keyBy('barcode');

                $existingLots = ProductLot::whereIn('product_id', $existingProducts->pluck('id'))
                    ->get()
                    ->keyBy('product_id');

                $validCategoryIds   = DB::table('categories')->pluck('id')->flip()->toArray();
                $validLaboratoryIds = DB::table('laboratories')->pluck('id')->flip()->toArray();
                $validOriginIds     = DB::table('origins')->pluck('id')->flip()->toArray();
                $validGroupIds      = DB::table('groups_products')->pluck('id')->flip()->toArray();

                foreach ($productsData as $row) {
                    $barcode = trim((string)($row['code'] ?? ''));
                    $rawName = trim((string)($row['name'] ?? ''));
                    if ($barcode === '' || $rawName === '') {
                        continue;
                    }

                    $stock = max(0, (int)round((float)($row['stock'] ?? 0)));
                    $cost = max(0.0, (float)($row['cost_usd'] ?? 0));

                    $masterProduct = $masterMap[$barcode] ?? null;
                    $finalName = !empty($masterProduct['name']) ? trim((string)$masterProduct['name']) : $rawName;

                    if ($masterProduct) {
                        $stats['products_matched_master']++;
                    }

                    $product = $existingProducts->get($barcode);
                    $isNew = !$product;

                    $productData = [
                        'name' => $finalName,
                        'description' => $rawName,
                        'barcode' => $barcode,
                        'stock' => $stock,
                        'unit_cost' => $cost,
                        'sale_price' => $cost,
                        'iva' => false,
                        'is_colombian_origin' => false,
                        'is_novaventa' => false,
                        'psychotropic' => false,
                        'is_active' => true,
                        'is_deleted' => false,
                        'is_favorite' => false,
                        'no_pvp' => false,
                        'lotification_completed' => true,
                        'active_ingredient' => !empty($masterProduct['active_ingredient']) ? trim((string)$masterProduct['active_ingredient']) : 'N/A',
                    ];

                    if ($masterProduct) {
                        if (!empty($masterProduct['laboratory_id']) && isset($validLaboratoryIds[(int)$masterProduct['laboratory_id']])) {
                            $productData['laboratory_id'] = (int)$masterProduct['laboratory_id'];
                        }
                        if (!empty($masterProduct['category_id']) && isset($validCategoryIds[(int)$masterProduct['category_id']])) {
                            $productData['category_id'] = (int)$masterProduct['category_id'];
                        }
                        if (!empty($masterProduct['origin_id']) && isset($validOriginIds[(int)$masterProduct['origin_id']])) {
                            $productData['origin_id'] = (int)$masterProduct['origin_id'];
                        }
                        if (!empty($masterProduct['group_id']) && isset($validGroupIds[(int)$masterProduct['group_id']])) {
                            $productData['group_id'] = (int)$masterProduct['group_id'];
                        }
                    }

                    try {
                        if ($isNew) {
                            $product = Product::create($productData);
                            $existingProducts->put($barcode, $product);
                            $stats['products_created']++;
                        } else {
                            if ($product->trashed()) {
                                $product->restore();
                            }
                            $product->update($productData);
                            $stats['products_updated']++;
                        }

                        $lot = $existingLots->get($product->id);
                        if ($lot) {
                            $lot->update([
                                'quantity'   => $stock,
                                'unit_cost'  => $cost,
                                'amount_usd' => round($stock * $cost, 2),
                            ]);
                        } else {
                            $lot = ProductLot::create([
                                'product_id'      => $product->id,
                                'lot_number'      => 'LOT-INICIAL',
                                'quantity'        => $stock,
                                'unit_cost'       => $cost,
                                'amount_usd'      => round($stock * $cost, 2),
                                'expiration_date' => Carbon::now()->addYear()->format('Y-m-d'),
                            ]);
                            $existingLots->put($product->id, $lot);
                        }
                    } catch (\Throwable $pe) {
                        Log::warning("Error importando producto {$barcode} ({$finalName}): " . $pe->getMessage());
                    }
                }
            }

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
                try {
                    $clientId = !empty($c['existing_id']) ? (int)$c['existing_id'] : null;
                    $cleanIdent = trim((string)($c['identification'] ?? ($c['code'] ?? '')));
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
                            } elseif (str_starts_with($cleanIdent, 'E')) {
                                $identType = 'E-';
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
                } catch (\Throwable $ce) {
                    Log::warning("Error importando cliente de a2: " . $ce->getMessage());
                }
            }

            return $stats;
        });
    }

    /**
     * Procesa la sincronización en streaming continuo proveniente del agente local de a2.
     * Inserta ventas, reduce stock de lotes e ingresa nuevos clientes o cuentas por pagar.
     */
    public function processStreamSync(array $payload): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        return DB::transaction(function () use ($payload) {
            $sellerId = Auth::id() ?: 1;
            $sales = $payload['sales'] ?? [];
            $clients = $payload['clients'] ?? [];
            $payables = $payload['payables'] ?? [];

            $stats = [
                'sales_synced'          => 0,
                'order_details_created' => 0,
                'clients_synced'        => 0,
                'payables_synced'       => 0,
                'sales_skipped'         => 0,
            ];

            // 1. Clientes nuevos del streaming
            foreach ($clients as $c) {
                try {
                    $cleanIdent = trim((string)($c['identification'] ?? ($c['code'] ?? '')));
                    if (!empty($cleanIdent)) {
                        $exists = Client::where('identification', $cleanIdent)->exists();
                        if (!$exists) {
                            $identType = 'V-';
                            if (str_starts_with($cleanIdent, 'J') || str_starts_with($cleanIdent, 'G')) {
                                $identType = substr($cleanIdent, 0, 1) . '-';
                            } elseif (str_starts_with($cleanIdent, 'E')) {
                                $identType = 'E-';
                            }
                            Client::create([
                                'identification_type' => $identType,
                                'identification'      => $cleanIdent,
                                'name'                => trim((string)($c['name'] ?? 'Cliente a2')),
                                'phone'               => !empty($c['phone']) ? trim((string)$c['phone']) : null,
                                'address'             => !empty($c['address']) ? trim((string)$c['address']) : null,
                                'client_type'         => Client::CLIENT_TYPE_NUEVO,
                                'status'              => 1,
                                'user_id'             => $sellerId,
                                'balance'             => 0.0,
                            ]);
                            $stats['clients_synced']++;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[A2 Stream] Error procesando cliente: ' . $e->getMessage());
                }
            }

            // 2. Resolver cliente genérico para ventas sin identificación
            $genericClient = Client::where('identification', '01001')
                ->orWhere('identification', '01-001')
                ->orWhere('name', 'like', '%Consumidor Final%')
                ->first();

            if (!$genericClient) {
                $genericClient = Client::create([
                    'identification_type' => 'V-',
                    'identification'      => '01001',
                    'name'                => 'Consumidor Final',
                    'client_type'         => Client::CLIENT_TYPE_NUEVO,
                    'status'              => 1,
                    'user_id'             => $sellerId,
                    'balance'             => 0.0,
                ]);
            }

            // 3. Ventas nuevas del streaming
            if (!empty($sales)) {
                // Pre-cargar productos por código de barras
                $allBarcodes = [];
                foreach ($sales as $s) {
                    foreach ($s['items'] ?? [] as $item) {
                        $code = trim((string)($item['code'] ?? ''));
                        if (!empty($code)) {
                            $allBarcodes[] = $code;
                        }
                    }
                }

                $products = Product::withoutGlobalScope('not_deleted')
                    ->whereIn('barcode', array_values(array_unique($allBarcodes)))
                    ->get()
                    ->keyBy('barcode');

                $productLots = ProductLot::whereIn('product_id', $products->pluck('id'))
                    ->where('quantity', '>', 0)
                    ->orderBy('expiration_date', 'asc')
                    ->get()
                    ->groupBy('product_id');

                foreach ($sales as $sale) {
                    $doc = trim((string)($sale['document'] ?? ''));
                    if (empty($doc)) {
                        continue;
                    }

                    // Idempotencia: Verificar si la orden ya existe por el identificador a2
                    $alreadyExists = Order::where('notes', 'like', "%[a2_doc:{$doc}]%")
                        ->orWhere('notes', 'like', "%a2_doc:{$doc}%")
                        ->exists();

                    if ($alreadyExists) {
                        $stats['sales_skipped']++;
                        continue;
                    }

                    $saleDate = !empty($sale['date']) ? Carbon::parse($sale['date']) : Carbon::now();
                    $totalUsd = (float)($sale['total_amount'] ?? 0.0);

                    // Resolver cliente de la venta si viene especificado
                    $targetClientId = $genericClient->id;
                    $clientCode = trim((string)($sale['client_code'] ?? ''));
                    if (!empty($clientCode)) {
                        $matched = Client::where('identification', $clientCode)->first();
                        if ($matched) {
                            $targetClientId = $matched->id;
                        }
                    }

                    $order = Order::create([
                        'client_id'               => $targetClientId,
                        'seller_id'               => $sellerId,
                        'total_amount'            => $totalUsd,
                        'money_returns'           => 0.0,
                        'usd_conversion'          => 1.0,
                        'total_cost'              => 0.0,
                        'taxable_base'            => $totalUsd,
                        'currency'                => 'USD',
                        'order_date'              => $saleDate,
                        'status'                  => Order::COMPLETED,
                        'has_multiple_currencies' => false,
                        'total_amount_usd'        => $totalUsd,
                        'notes'                   => "[a2_doc:{$doc}] Sincronizado automáticamente desde a2 Softway",
                    ]);

                    $orderTotalCost = 0.0;

                    foreach ($sale['items'] ?? [] as $item) {
                        $barcode = trim((string)($item['code'] ?? ''));
                        $qty = max(1.0, (float)($item['quantity'] ?? 1.0));
                        $price = (float)($item['price'] ?? 0.0);

                        $product = $products->get($barcode);
                        $productId = $product ? $product->id : null;
                        $cost = $product ? (float)($product->unit_cost ?? 0.0) : 0.0;
                        $orderTotalCost += ($cost * $qty);

                        // Rebajar inventario y lotes
                        if ($product) {
                            // Rebajar stock global del producto
                            $product->decrement('stock', (int)round($qty));
                            $product->update(['external_sales_date' => $saleDate]);

                            // Rebajar de los lotes activos (FIFO por fecha de vencimiento)
                            $remainingQty = $qty;
                            $lots = $productLots->get($product->id) ?? collect();
                            foreach ($lots as $lot) {
                                if ($remainingQty <= 0) {
                                    break;
                                }
                                $toDeduct = min($lot->quantity, $remainingQty);
                                $lot->decrement('quantity', $toDeduct);
                                $lot->amount_usd = round($lot->quantity * $lot->unit_cost, 2);
                                $lot->save();
                                $remainingQty -= $toDeduct;
                            }
                        }

                        OrderDetail::create([
                            'order_id'                 => $order->id,
                            'product_id'               => $productId,
                            'product_type'             => 'product',
                            'quantity'                 => $qty,
                            'quantity_expiration'      => 0.0,
                            'price'                    => $price,
                            'price_bs'                 => 0.0,
                            'price_before_discount'    => $price,
                            'price_before_discount_bs' => 0.0,
                            'unit_cost'                => $cost,
                            'discount_percentage'      => 0.0,
                            'notes'                    => "a2 item [{$barcode}]",
                        ]);

                        $stats['order_details_created']++;
                    }

                    $order->update(['total_cost' => round($orderTotalCost, 2)]);
                    $stats['sales_synced']++;
                }
            }

            Log::info('[A2 Stream Sync] Procesamiento completado', $stats);

            return $stats;
        });
    }
}
