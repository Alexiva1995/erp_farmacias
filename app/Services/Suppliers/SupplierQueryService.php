<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\AutoOrder;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Http\Requests\StoreProductIntoautoOrderRequest;
use App\Models\SupplierConnection;
use App\Models\SupplierConnectionStatus;
use App\Enums\AutoOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Contracts\Repositories\SupplierRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

class SupplierQueryService
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository
    ) {}

    /**
     * Prepares the base query for suppliers.
     */
    private function getBaseQuery(): Builder
    {
        $currentYearStart = \Carbon\Carbon::now()->startOfYear();

        return $this->supplierRepository->getQuery()
            ->withSum(['invoices as invoices_sum_total_usd' => function ($q) use ($currentYearStart) {
                $q->where(function ($sq) {
                    $sq->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })->whereDate('payment_date', '>=', $currentYearStart);
            }], 'total_usd');
    }

    /**
     * Applies filters to the supplier query.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters["q"])) {
            $searchTerm = "%{$filters["q"]}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery
                    ->where("suppliers.name", "like", $searchTerm)
                    ->orWhere("suppliers.sales_phone", "like", $searchTerm)
                    ->orWhere("suppliers.collections_phone", "like", $searchTerm)
                    ->orWhere("suppliers.id", "like", $searchTerm);
            });
        }

        if (!empty($filters["type"])) {
            $query->where("suppliers.type", $filters["type"]);
        }

        if (!empty($filters["debtStatus"])) {
            $currentYearStart = \Carbon\Carbon::now()->startOfYear();
            if ($filters["debtStatus"] === "with_debt") {
                $query->whereHas("invoices", function ($q) use ($currentYearStart) {
                    $q->where(function ($sq) {
                        $sq->whereNull("status_payment")
                            ->orWhere("status_payment", "!=", 1);
                    })->whereDate("payment_date", ">=", $currentYearStart);
                });
            } elseif ($filters["debtStatus"] === "no_debt") {
                $query->whereDoesntHave("invoices", function ($q) use ($currentYearStart) {
                    $q->where(function ($sq) {
                        $sq->whereNull("status_payment")
                            ->orWhere("status_payment", "!=", 1);
                    })->whereDate("payment_date", ">=", $currentYearStart);
                });
            }
        }

        return $query;
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy("suppliers.name", "asc");
        }

        switch ($sortBy) {
            case "latestScore.score":
                return $query
                    ->leftJoin("supplier_scores as ss", function ($join) {
                        $join->on("ss.supplier_id", "=", "suppliers.id");
                    })
                    ->orderBy("ss.score", $orderBy)
                    ->orderBy("ss.evaluated_on", "desc")
                    ->select("suppliers.*");

            case "debt":
                $currentYearStart = \Carbon\Carbon::now()->startOfYear()->toDateString();
                $subDebt = DB::raw("(
                    SELECT SUM(COALESCE(i.total_usd, 0)) 
                    FROM invoices i
                    WHERE i.supplier_id = suppliers.id
                    AND (i.status_payment IS NULL OR i.status_payment != 1)
                    AND DATE(i.payment_date) >= '{$currentYearStart}'
                )");
                return $query->orderBy($subDebt, $orderBy);

            case "id":
            case "name":
                return $query->orderBy("suppliers.{$sortBy}", $orderBy);
        }

        return $query;
    }

    /**
     * Returns a filtered query for suppliers.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            "q" => $request->q,
            "type" => $request->type,
            "debtStatus" => $request->debtStatus,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input("sortBy"), $request->input("orderBy", "asc"));

        return $query;
    }

    /**
     * Retrieves the laboratories associated with a supplier.
     */
    public function getLaboratories(Supplier $supplier): Collection
    {
        return $supplier->laboratoryLinks()->with("laboratory")->get();
    }

    /**
     * Retrieves unpaid invoices grouped by payment date for a given supplier.
     */
    public function getUnpaidInvoicesByDate(Supplier $supplier): SupportCollection
    {
        return Invoice::query()
            ->where("supplier_id", $supplier->id)
            ->whereHas("payments", fn($q) => $q->where("status", "unpaid"))
            ->with(["payments" => fn($q) => $q->where("status", "unpaid")])
            ->get()
            ->flatMap(function ($invoice) {
                return $invoice->payments->map(function ($payment) use ($invoice) {
                    return [
                        "id" => $invoice->id,
                        "invoice_number" => $invoice->invoice_number,
                        "total_amount" => $invoice->total_amount,
                        "payment_date" => $payment->payment_date,
                    ];
                });
            })
            ->groupBy("payment_date");
    }

    public function getPaymentRules(Supplier $supplier): Collection
    {
        return $supplier->paymentRules()->get();
    }

    public function getDiscounts(Supplier $supplier): Collection
    {
        return $supplier->discounts()->get();
    }

    public function storeSupplierConnectionData(Supplier $supplier, array $data)
    {
        // Logs DIRECTO a archivo para asegurar que se escriban SIEMPRE
        $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
        $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 storeSupplierConnectionData INICIADO - Supplier ID: {$supplier->id}, Name: {$supplier->name}, Products: " . count($data["products"] ?? []) . ", Invoices: " . count($data["invoices"] ?? []) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log($logMessage);

        try {
            $products = $data["products"] ?? [];
            $invoices = $data["invoices"] ?? [];

            // Borrado automático de productos previos del proveedor que NO estén en auto-orden
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🧹 Iniciando limpieza automática de productos para el proveedor: {$supplier->id}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            $deletedCount = $supplier->productSuppliers()
                ->whereDoesntHave('autoOrderDetails')
                ->delete();
            
            $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ Limpieza completada. Productos eliminados: {$deletedCount}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);


            // No filtramos por grupo para permitir que suban todos los registros (duplicados incluidos si no tienen ID)
            $uniqueProducts = $products;

            $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 Productos después de asignación - Total: " . count($uniqueProducts) . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log($logMessage);


            // Cargar facturas y números de control existentes globalmente y para este proveedor
            $allInvoiceNumbers = Invoice::pluck('invoice_number')
                ->filter()
                ->map(fn($n) => strtoupper(trim((string)$n)))
                ->flip()
                ->toArray();

            $existingSupplierInvoices = Invoice::where('supplier_id', $supplier->id)
                ->get(['invoice_number', 'control_number']);

            $existingControls = $existingSupplierInvoices
                ->pluck('control_number')
                ->filter()
                ->map(fn($c) => strtoupper(trim((string)$c)))
                ->flip()
                ->toArray();

            $existingNormalizedNumbers = [];
            foreach ($existingSupplierInvoices as $inv) {
                $raw = strtoupper(trim((string)$inv->invoice_number));
                if (!empty($raw)) {
                    $existingNormalizedNumbers[$raw] = true;
                    $stripped = ltrim($raw, 'ABFCD');
                    $strippedNoZeroes = ltrim($stripped, '0');
                    $existingNormalizedNumbers[$stripped] = true;
                    if (!empty($strippedNoZeroes)) {
                        $existingNormalizedNumbers[$strippedNoZeroes] = true;
                    }
                    if (str_starts_with($raw, '70') && strlen($raw) >= 6) {
                        $sub = ltrim(substr($raw, 2), '0');
                        if (!empty($sub)) {
                            $existingNormalizedNumbers[$sub] = true;
                        }
                    }
                }
            }

            $filteredInvoices = collect($invoices)
                ->filter(function ($invoice) use ($existingControls, $existingNormalizedNumbers, $allInvoiceNumbers) {
                    $number = strtoupper(trim((string)($invoice['header']['invoice_number'] ?? '')));
                    $control = strtoupper(trim((string)($invoice['header']['control_number'] ?? '')));

                    if (empty($number)) {
                        return false;
                    }

                    // 0. Validar por número de factura existente a nivel global (evitar violar unicidad en DB)
                    if (isset($allInvoiceNumbers[$number])) {
                        Log::warning("Factura filtrada: Ya existe en la base de datos", ['number' => $number]);
                        return false;
                    }

                    // 1. Validar por número de control fiscal idéntico
                    if (!empty($control) && isset($existingControls[$control])) {
                        Log::warning("Factura filtrada: Ya existe una factura con el número de control '{$control}' para este proveedor", ['number' => $number]);
                        return false;
                    }

                    // 2. Validar por número de factura normalizado
                    $stripped = ltrim($number, 'ABFCD');
                    $strippedNoZeroes = ltrim($stripped, '0');

                    if (isset($existingNormalizedNumbers[$number]) ||
                        isset($existingNormalizedNumbers[$stripped]) ||
                        (!empty($strippedNoZeroes) && isset($existingNormalizedNumbers[$strippedNoZeroes]))) {
                        Log::warning("Factura filtrada: Ya existe en el ERP bajo número normalizado", ['number' => $number]);
                        return false;
                    }

                    return true;
                })->values()->toArray();


            // Procesar productos FUERA de la transacción para evitar rollback si uno falla
            // NO eliminar ningún producto existente
            // NO actualizar ningún producto existente
            // SOLO crear nuevos registros con todos los productos del archivo

            // Procesar CADA producto del archivo individualmente
            // SIEMPRE crear nuevos registros, NUNCA actualizar ni eliminar existentes
            $totalProductos = count($uniqueProducts);
            $insertados = 0;
            $errores = 0;

            $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 Iniciando inserción de productos - Total: {$totalProductos}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);

            $nowStr = now()->toDateTimeString();
            $todayStr = now()->toDateString();

            $templateRow = [
                'supplier_id' => $supplier->id,
                'product_id' => null,
                'cod_supplier' => null,
                'barcode_match' => null,
                'name' => null,
                'laboratory' => null,
                'active_ingredient' => null,
                'discount_percentage' => 0,
                'is_ai_matched' => false,
                'is_active' => true,
                'expiration' => null,
                'quantity' => 1000,
                'unit_cost' => 0,
                'unit_cost_usd' => 0,
                'unit_cost_with_discount' => 0,
                'unit_cost_usd_with_discount' => 0,
                'connection_date' => $todayStr,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];

            $batchRows = [];
            foreach ($uniqueProducts as $productData) {
                $row = $templateRow;
                foreach (array_keys($templateRow) as $key) {
                    if (array_key_exists($key, $productData) && $productData[$key] !== null) {
                        $row[$key] = $productData[$key];
                    }
                }

                $row['supplier_id'] = $supplier->id;
                $row['unit_cost'] = is_numeric($row['unit_cost']) ? (float)$row['unit_cost'] : 0;
                $row['unit_cost_usd'] = is_numeric($row['unit_cost_usd']) ? (float)$row['unit_cost_usd'] : 0;
                $row['discount_percentage'] = is_numeric($row['discount_percentage']) ? (float)$row['discount_percentage'] : 0;
                $row['is_ai_matched'] = (bool)($row['is_ai_matched'] ?? false);
                $row['is_active'] = (bool)($row['is_active'] ?? true);

                if (empty($row['unit_cost_with_discount']) || !is_numeric($row['unit_cost_with_discount'])) {
                    $row['unit_cost_with_discount'] = $row['unit_cost'];
                } else {
                    $row['unit_cost_with_discount'] = (float)$row['unit_cost_with_discount'];
                }

                if (empty($row['unit_cost_usd_with_discount']) || !is_numeric($row['unit_cost_usd_with_discount'])) {
                    $row['unit_cost_usd_with_discount'] = $row['unit_cost_usd'];
                } else {
                    $row['unit_cost_usd_with_discount'] = (float)$row['unit_cost_usd_with_discount'];
                }

                if (empty($row['quantity']) || !is_numeric($row['quantity']) || (float)$row['quantity'] <= 0) {
                    $row['quantity'] = 1000;
                } else {
                    $row['quantity'] = (float)$row['quantity'];
                }

                if (empty($row['connection_date'])) {
                    $row['connection_date'] = $todayStr;
                }

                $batchRows[] = $row;
            }

            foreach (array_chunk($batchRows, 500) as $chunk) {
                try {
                    DB::table('product_suppliers')->insertOrIgnore($chunk);
                    $insertados += count($chunk);
                } catch (\Throwable $e) {
                    $errores += count($chunk);
                    Log::error("Error insertando lote de productos", ['error' => $e->getMessage()]);
                }
            }

            $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🟢 Finalizada inserción - Total: {$totalProductos}, Insertados: {$insertados}, Errores: {$errores}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log($logMessage);


            // Procesar facturas de forma segura e individual
            foreach ($filteredInvoices as $invoice) {
                try {
                    DB::transaction(function () use ($supplier, $invoice) {
                        $header = $invoice['header'];
                        $lines = $invoice['lines'];

                        $totalAmount = $header['total_amount'] ?? null;
                        if ($totalAmount === null || $totalAmount === '') {
                            $totalUsd = floatval($header['total_usd'] ?? 0);
                            $rate = floatval($header['exchange_rate'] ?? 1);
                            $totalAmount = round($totalUsd * $rate, 2);
                        }

                        $invoiceModel = $supplier->invoices()->create([
                            ...Arr::only($header, Invoice::FILLABLEHEADER),
                            'total_amount' => $totalAmount,
                            'status' => $invoice['status'] ?? 'pending',
                            'uploaded_by' => auth()->id() ?? 1,
                            'registered_by' => auth()->id() ?? 1,
                        ]);

                        // ✅ Obtener exchange_rate del header
                        $exchangeRate = floatval($header['exchange_rate'] ?? 1);
                        $isVitaclinics = str_contains(strtolower($supplier->name ?? ''), 'vitalclinic')
                            || str_contains(strtolower($supplier->name ?? ''), 'vitaclinic')
                            || in_array($supplier->id, [15, 1009]);

                        $details = [];
                        foreach ($lines as $line) {
                            $lineData = Arr::only($line, InvoiceDetail::FILLABLEDETAILS);

                            // 🔍 Vincular producto por barcode si no tiene product_id
                            if (empty($lineData['product_id']) && !empty($line['barcode'])) {
                                $product = Product::withoutGlobalScope('not_deleted')
                                    ->withTrashed()
                                    ->where('barcode', $line['barcode'])
                                    ->first();
                                
                                if ($product) {
                                    if ($product->trashed()) {
                                        $product->restore();
                                    }
                                } elseif (!empty($line['name']) || !empty($line['descripcion_producto'])) {
                                    // 🆕 Crear producto en modo borrador (no visible hasta finalizar factura)
                                    $product = Product::create([
                                        'name'       => $line['name'] ?? $line['descripcion_producto'] ?? 'PRODUCTO',
                                        'barcode'    => $line['barcode'],
                                        'unit_cost'  => floatval($line['unit_cost'] ?? 0),
                                        'sale_price' => floatval($line['unit_cost'] ?? 0),
                                        'is_active'  => true,
                                        'is_deleted' => true,
                                    ]);
                                }

                                if ($product) {
                                    $lineData['product_id'] = $product->id;
                                }
                            }

                            if (!isset($lineData['total_cost']) && isset($line['total_amount'])) {
                                $lineData['total_cost'] = floatval($line['total_amount']);
                            }

                            // ✅ Si es Vitaclinics y tiene exchange_rate, multiplicar unit_cost
                            if ($isVitaclinics && $exchangeRate > 1) {
                                $unitCost = floatval($lineData['unit_cost'] ?? 0);
                                $lineData['unit_cost'] = number_format($unitCost * $exchangeRate, 2, '.', '');

                                // Recalcular total_cost también
                                $quantity = floatval($lineData['quantity'] ?? 0);
                                $lineData['total_cost'] = number_format($lineData['unit_cost'] * $quantity, 2, '.', '');
                            }

                            $details[] = [
                                ...$lineData,
                                'invoice_id' => $invoiceModel->id,
                            ];
                        }

                        $invoiceModel->details()->createMany($details);
                    });
                } catch (\Throwable $invError) {
                    Log::error("Error guardando factura individual", [
                        'supplier_id' => $supplier->id,
                        'invoice' => $invoice['header']['invoice_number'] ?? null,
                        'error' => $invError->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'inserted_products' => $insertados,
                'total_products' => $totalProductos,
                'errors_count' => $errores,
                'invoices_count' => count($filteredInvoices),
            ];
        } catch (\Throwable $e) {
            Log::error("Error in storeSupplierConnectionData: " . $e->getMessage());
            report($e);
            throw new \Exception("Error al guardar los datos del proveedor en base de datos: " . $e->getMessage(), 0, $e);
        }
    }

    public function getSupplierConnections(Request $request)
    {
        $filters = $request->query();
        $perPage = (int) ($filters["perPage"] ?? $filters["itemsPerPage"] ?? $filters["per_page"] ?? 10);
        if ($perPage <= 0) {
            $perPage = 1000;
        }
        $page = (int) ($filters["page"] ?? $request->query("page", 1));

        // Buscamos el parámetro 'search' (enviado desde el frontend)
        // O mantenemos compatibilidad si enviasen 'selectedSupplier' como texto
        $searchTerm = $filters["search"] ?? $filters["selectedSupplier"] ?? null;

        $paginated = DB::table("suppliers")
            ->select(
                "suppliers.name as name",
                "suppliers.id",
                "suppliers.public_token",
                DB::raw("COALESCE(suppliers.is_active, 1) as is_active"),
                DB::raw(
                    "COALESCE(supplier_connections.last_connection, 'No se ha establecido conexión') as last_connection",
                ),
                DB::raw("UPPER(COALESCE(CASE WHEN supplier_connections.type = 'file' THEN 'Archivo Excel' ELSE supplier_connections.type END, 'No registrado')) as type"),
            )
            ->leftJoin("supplier_connections", "supplier_id", "=", "suppliers.id")
            ->whereNull("suppliers.deleted_at")
            ->when($searchTerm, function ($query) use ($searchTerm) {
                // Buscamos coincidencia parcial en el nombre
                $query->where("suppliers.name", "LIKE", "%{$searchTerm}%");
            })
            ->orderBy("suppliers.is_active", "desc")
            ->orderBy("suppliers.name", "asc")
            ->paginate($perPage, ["*"], "page", $page);

        return $paginated;
    }

    public function getSupplierProducts(Supplier $supplier, Request $request)
    {
        $filters = $request->query();
        $perPage = $filters["perPage"] ?? 10;

        $paginated = DB::table("product_suppliers")
            ->select(
                DB::raw("COALESCE(product_suppliers.product_id, 'N/A') as product_id"),
                "product_suppliers.cod_supplier",
                "product_suppliers.barcode_match",
                DB::raw("COALESCE(NULLIF(product_suppliers.laboratory, ''), 'N/A') as laboratory"),
                "product_suppliers.id",
                "product_suppliers.unit_cost",
                "product_suppliers.unit_cost_usd",
                "product_suppliers.unit_cost_with_discount",
                "product_suppliers.unit_cost_usd_with_discount",
                DB::raw("ROUND(CASE WHEN product_suppliers.unit_cost > 0 AND product_suppliers.unit_cost_with_discount > 0 AND product_suppliers.unit_cost_with_discount < product_suppliers.unit_cost THEN (1 - (product_suppliers.unit_cost_with_discount / product_suppliers.unit_cost)) * 100 ELSE 0 END, 2) as discount_percentage"),
                "product_suppliers.quantity",
                "product_suppliers.expiration",
                DB::raw("COALESCE(NULLIF(product_suppliers.name, ''), products.name, 'N/A') as name"),
            )
            ->leftJoin("products", "products.id", "=", "product_suppliers.product_id")
            ->where("product_suppliers.supplier_id", "=", $supplier->id)
            ->orderBy("product_suppliers.name", "asc")
            ->paginate($perPage);

        return $paginated;
    }

    public function addDiscountsToProducts(Supplier $supplier): void
    {
        $supplierId = $supplier->id;

        try {
            DB::transaction(function () use ($supplierId) {
                $factor = DB::scalar(
                    "SELECT COALESCE(1 - MAX(rate), 1)
                              FROM (
                                    SELECT discount_percentage / 100 AS rate
                                      FROM supplier_discounts
                                     WHERE supplier_id = ?
                                     UNION ALL
                                    SELECT discount_percentage / 100
                                      FROM payment_rules
                                     WHERE supplier_id = ?
                                   ) AS x",
                    [$supplierId, $supplierId],
                );

                if ($factor === null) {
                    return;
                }
                DB::update(
                    'UPDATE product_suppliers
                       SET unit_cost_with_discount    = ROUND(unit_cost     * ?, 2),
                           unit_cost_usd_with_discount = ROUND(unit_cost_usd * ?, 2)
                     WHERE supplier_id = ?',
                    [$factor, $factor, $supplierId],
                );
            });
        } catch (\Throwable $e) {
            Log::error($e);
        }
    }

    public function getProducts(Request $request)
    {
        $laboratoryId = $request->query("laboratoryId");
        $supplierId = $request->query("supplierId");
        $perPage = $request->query("perPage", 10) ?? 10;

        $search = trim($request->query('q') ?? '');

        $originId = $request->query("originId");

        $hasStock = $request->has('hasStock')
            ? filter_var($request->query("hasStock"), FILTER_VALIDATE_BOOLEAN)
            : null;

        $isStrictSearch = filter_var($request->query("isStrictSearch"), FILTER_VALIDATE_BOOLEAN);

        $sortBy = $request->query("sortBy", "name");
        $sortOrder = $request->query("order", "asc");

        $enableDiscounts = filter_var($request->query("enableDiscounts", false), FILTER_VALIDATE_BOOLEAN);

        $sortableColumns = [
            'name' => 'product_suppliers.name',
            'unit_cost' => $enableDiscounts 
                ? DB::raw("CASE WHEN product_suppliers.unit_cost_usd_with_discount > 0 THEN product_suppliers.unit_cost_usd_with_discount ELSE product_suppliers.unit_cost_usd END")
                : 'product_suppliers.unit_cost_usd',
            'unit_cost_bs' => $enableDiscounts 
                ? DB::raw("CASE WHEN product_suppliers.unit_cost_with_discount > 0 THEN product_suppliers.unit_cost_with_discount ELSE product_suppliers.unit_cost END")
                : 'product_suppliers.unit_cost',
            'unit_cost_usd' => $enableDiscounts 
                ? DB::raw("CASE WHEN product_suppliers.unit_cost_usd_with_discount > 0 THEN product_suppliers.unit_cost_usd_with_discount ELSE product_suppliers.unit_cost_usd END")
                : 'product_suppliers.unit_cost_usd',
            'final_cost_bs' => DB::raw("CASE WHEN product_suppliers.unit_cost_with_discount > 0 THEN product_suppliers.unit_cost_with_discount ELSE product_suppliers.unit_cost END"),
            'final_cost_usd' => DB::raw("CASE WHEN product_suppliers.unit_cost_usd_with_discount > 0 THEN product_suppliers.unit_cost_usd_with_discount ELSE product_suppliers.unit_cost_usd END"),
            'expiration' => 'product_suppliers.expiration'
        ];

        $sortColumn = $sortableColumns[$sortBy] ?? 'product_suppliers.name';

        $results = ProductSupplier::query()
            ->where(function ($q) {
                $q->where('product_suppliers.updated_at', '>=', now()->subDays(60))
                  ->orWhere('product_suppliers.created_at', '>=', now()->subDays(60));
            })
            ->where(function ($q) {
                $q->where('product_suppliers.unit_cost_usd', '>', 0)
                  ->orWhere('product_suppliers.unit_cost', '>', 0);
            })
            ->with([
                'product.laboratory:id,name',
                'supplier:id,name'
            ])
            ->select([
                "product_suppliers.id as id",
                "product_suppliers.product_id as product_id",
                "product_suppliers.name as name",
                DB::raw("COALESCE(NULLIF(product_suppliers.laboratory, ''), laboratories.name, 'N/A') as laboratory_name"),
                "suppliers.name as supplier_name",
                "product_suppliers.unit_cost as unit_cost_bs",
                "product_suppliers.unit_cost_usd as unit_cost_usd",
                DB::raw("COALESCE(product_suppliers.unit_cost_with_discount, 0) as final_cost_bs"),
                DB::raw("COALESCE(product_suppliers.unit_cost_usd_with_discount, 0) as final_cost_usd"),
                DB::raw("COALESCE(products.unit_cost, 0) as our_unit_cost_usd"),
                "product_suppliers.expiration as expiration",
                "product_suppliers.active_ingredient as active_ingredient",
                "product_suppliers.is_active as is_active",
            ])
            ->leftJoin("products", "products.id", "=", "product_suppliers.product_id")
            ->leftJoin("laboratories", "laboratories.id", "=", "products.laboratory_id")
            ->leftJoin("suppliers", "suppliers.id", "=", "product_suppliers.supplier_id")
            ->when($hasStock !== null, function ($query) {
                $query->leftJoin('product_lots', function ($join) {
                    $join->on('product_lots.product_id', '=', 'products.id')
                         ->where('product_lots.expiration_date', '>=', DB::raw('CURDATE()'))
                         ->where('product_lots.quantity', '>', 0);
                })
                ->groupBy([
                    'product_suppliers.id',
                    'product_suppliers.product_id',
                    'product_suppliers.name',
                    'suppliers.name',
                    'product_suppliers.unit_cost',
                    'product_suppliers.unit_cost_usd',
                    'product_suppliers.unit_cost_with_discount',
                    'product_suppliers.unit_cost_usd_with_discount',
                    'product_suppliers.expiration',
                    'product_suppliers.active_ingredient',
                    'product_suppliers.is_active',
                    'product_suppliers.laboratory',
                    'laboratories.name',
                    'products.id'
                ]);
            })
            ->when(!empty($search), function ($query) use ($search, $isStrictSearch) {
                if ($isStrictSearch) {
                    $query->where(function ($q) use ($search) {
                        // Coincidencia estricta por palabra independiente compatible con MySQL 8+ / MariaDB
                        $escapedSearch = preg_quote($search, '/');
                        $q->whereRaw("product_suppliers.name REGEXP ?", ['(^|[[:space:][:punct:]])' . $escapedSearch . '([[:space:][:punct:]]|$)'])
                            ->orWhereRaw("product_suppliers.active_ingredient REGEXP ?", ['(^|[[:space:][:punct:]])' . $escapedSearch . '([[:space:][:punct:]]|$)'])
                            ->orWhereRaw("product_suppliers.laboratory REGEXP ?", ['(^|[[:space:][:punct:]])' . $escapedSearch . '([[:space:][:punct:]]|$)'])
                            ->orWhereRaw("laboratories.name REGEXP ?", ['(^|[[:space:][:punct:]])' . $escapedSearch . '([[:space:][:punct:]]|$)'])
                            ->orWhere('product_suppliers.barcode_match', '=', $search)
                            ->orWhere('product_suppliers.id', '=', $search)
                            ->orWhere('product_suppliers.product_id', '=', $search);
                    });
                } else {
                    $words = array_filter(explode(' ', $search));
                    foreach ($words as $word) {
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('product_suppliers.name', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.active_ingredient', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.laboratory', 'like', "%{$word}%")
                                ->orWhere('laboratories.name', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.barcode_match', 'like', "%{$word}%");
                        });
                    }
                }
            })
            ->when(!empty($originId), function ($query) use ($originId) {
                $query->where('products.origin_id', $originId);
            })
            ->when($supplierId, function ($query) use ($supplierId) {
                $query->where("product_suppliers.supplier_id", $supplierId);
            })
            ->when(!empty($request->query('laboratoryId')), function ($query) use ($request) {
                $labs = Arr::wrap($request->query('laboratoryId'));
                $query->whereIn("products.laboratory_id", $labs);
            })
            ->when(!empty($request->query('groupId')), function ($query) use ($request) {
                $groups = Arr::wrap($request->query('groupId'));
                $query->whereIn("products.group_id", $groups);
            })
            ->when($hasStock !== null, function ($query) use ($hasStock) {
                $query->havingRaw($hasStock ? "COALESCE(SUM(product_lots.quantity), 0) > 0" : "COALESCE(SUM(product_lots.quantity), 0) = 0");
            })
            ->orderBy('product_suppliers.is_active', 'desc')
            ->orderBy($sortColumn, $sortOrder)
            ->paginate($perPage);

        return $results;
    }

    public function getAvailableLaboratories()
    {
        $results = Laboratory::query()
            ->select(["id", "name"])
            ->orderBy("name", "asc")
            ->get();

        return $results;
    }

    /**
     * Obtiene los proveedores activos disponibles en el sistema.
     */
    public function getAvailableSuppliers(): Collection
    {
        return Supplier::query()
            ->where(function ($q) {
                $q->where('is_active', true)
                  ->orWhereNull('is_active');
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')
                  ->orWhere('is_deleted', false);
            })
            ->select(["id", "name"])
            ->orderBy("name", "asc")
            ->get();
    }

    public function addProductToOrder(\Illuminate\Http\Request $request)
    {
        $productId = $request->productId;
        $mainProductId = $request->main_product_id;
        if ($mainProductId === 'null' || $mainProductId === 'undefined' || !$mainProductId) {
            $mainProductId = null;
        }
        $quantity = $request->quantity;
        $discount = $request->boolean("discount");
        $product = ProductSupplier::find($productId);

        if (!$product) {
            return [
                'success' => false,
                'message' => 'Producto de proveedor no encontrado.',
            ];
        }

        if ($product->is_active === false) {
            return [
                'success' => false,
                'message' => 'Este producto se encuentra desactivado. Debe habilitarlo antes de añadirlo al pedido.',
            ];
        }

        $barcodeWarning = null;
        $mainProduct = $mainProductId ? Product::find($mainProductId) : null;
        // Si el producto principal no tiene barcode válido, ignorarlo en el asistente
        // hasta que el usuario le asigne uno manualmente (ignore_until = +1 año)
        if ($mainProduct && (empty($mainProduct->barcode) || strlen($mainProduct->barcode) < 6)) {
            if (empty($mainProduct->ignore_until) || \Carbon\Carbon::parse($mainProduct->ignore_until)->isPast()) {
                $mainProduct->update(['ignore_until' => now()->addDays(7)]);
            }
            $barcodeWarning = "Producto añadido al pedido. No se muestra en el asistente hasta que se le asigne un código de barras.";
        }

        if ($product && $mainProductId && $product->product_id != $mainProductId) {
            $product->update([
                'product_id' => $mainProductId
            ]);
        }

        $targetProductId = $mainProductId ?: ($product ? $product->product_id : null);

        $order = AutoOrder::where('supplier_id', $product->supplier_id)
            ->where('status', \App\Enums\AutoOrderStatus::PENDING)
            ->orderByDesc("created_at")
            ->first();

        $unitCost = $discount ? $product->unit_cost_usd_with_discount : $product->unit_cost_usd;
        $subtotal = $unitCost * $quantity;

        $detailPayload = [
            "product_id" => $mainProductId ?: ($product ? $product->product_id : null),
            "product_suppliers_id" => $productId,
            "quantity" => $quantity,
            "unit_cost" => $unitCost,
            "subtotal" => $subtotal
        ];


        if (isset($order)) {
            $existingDetail = $order->details()->where('product_suppliers_id', $productId)->first();
            if ($existingDetail) {
                $existingDetail->increment('quantity', $quantity);
                $existingDetail->increment('subtotal', $subtotal);
            } else {
                $order->details()->create($detailPayload);
                $order->increment("total_items", 1);
            }

            $order->increment("total_quantity", $quantity);
            $order->increment("total_amount", $subtotal);
        } else {
            // Calcular fecha de entrega tentativa basada en dispatch_days del proveedor
            $tentativeDate = null;
            $supplier = $product->supplier;
            $dispatchDays = $supplier->dispatch_days; // Ej: [1, 3, 5] o ["Monday", ...]
            
            if (!empty($dispatchDays) && is_array($dispatchDays)) {
                $today = now();
                $minDiff = 8; // Más de una semana
                foreach ($dispatchDays as $day) {
                    // Normalizar el día (pueden venir como nombres o números de ISO-8601 1=Mon, 7=Sun)
                    $targetDay = is_numeric($day) ? (int)$day : date('N', strtotime($day));
                    $currentDay = (int)$today->format('N');
                    
                    $diff = $targetDay - $currentDay;
                    if ($diff <= 0) $diff += 7; // Próxima semana
                    
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                    }
                }
                if ($minDiff < 8) {
                    $tentativeDate = $today->copy()->addDays($minDiff);
                }
            }

            $payload = [
                "supplier_id" => $product->supplier_id,
                "order_date" => now()->today(),
                "total_items" => 1,
                "total_quantity" => $quantity,
                "total_amount" => $subtotal,
                "tentative_delivery_date" => $tentativeDate,
            ];
            $order = AutoOrder::create($payload);
            $order->details()->create($detailPayload);
        }


        /*  if (isset($order)) {
              $order->details()->create([
                  "product_id" => $mainProduct->id,
                  "product_suppliers_id" => $productId,
                  "quantity" => $quantity,
                  "unit_cost" => $unitCost,
                  "subtotal" => $subtotal
              ]);

              $order->increment("total_items", 1);
              $order->increment("total_quantity", $quantity);
              $order->increment("total_amount", $subtotal);
          } else {
              $payload = [
                  "supplier_id" => $product->supplier_id,
                  "order_date" => now()->today(),
                  "total_items" => 1,
                  "total_quantity" => $quantity,
                  "total_amount" => $subtotal,
              ];
              $order = AutoOrder::create($payload);

              $order->details()->create([
                  "product_id" => $mainProduct->id,
                  "product_suppliers_id" => $productId,
                  "quantity" => $quantity,
                  "unit_cost" => $unitCost,
                  "subtotal" => $subtotal
              ]);
          }*/

        if ($mainProduct) {
            $updateData = ['is_ordered' => false];
            if (empty($product->barcode_match) || strlen(trim($product->barcode_match)) < 6) {
                $updateData['ignore_until'] = now()->addDays(7);
            }
            $mainProduct->update($updateData);
        }
        $product->decrement("quantity", $quantity);

        //return true;
        return [
            'success' => true,
            'warning' => $barcodeWarning
        ];


    }

    public function deleteProducts(Supplier $supplier)
    {
        // Solo eliminamos productos que NO tengan detalles de órdenes pendientes/enviadas
        // Si el producto está en una orden COMPLETADA (finalizada), permitimos el borrado del producto del catálogo
        $supplier->productSuppliers()
            ->whereDoesntHave('autoOrderDetails', function ($query) {
                $query->whereHas('autoOrder', function ($q) {
                    $q->where('status', '!=', AutoOrderStatus::COMPLETED->value);
                });
            })
            ->delete();

        return response()->json(["status" => "ok"]);
    }

    public function getRecentConnectionStatusesForUser(int $userId, int $minutes = 15): Collection
    {
        return SupplierConnectionStatus::with('supplier')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->latest('id')
            ->get();
    }



    public function storeConnection(array $data)
    {
        SupplierConnection::updateOrCreate(['supplier_id' => $data['supplier_id']], $data);
        return true;
    }

    public function getSupplierFirstConnection(Supplier $supplier)
    {
        return $supplier->connections()->first();
    }

    /**
     * Obtiene el resumen estadístico de proveedores
     */
    public function getSupplierSummaryStats(): array
    {
        // 1. Deuda Total (Facturas que salen exactamente en la vista Por Pagar)
        $pendingPaymentsService = app(\App\Services\PendingPayments\PendingPaymentsService::class);
        $pendingInvoices = $pendingPaymentsService->getPendingInvoices();
        $totalDebt = (float) $pendingInvoices->sum('total_usd');

        // 2. Total de Proveedores Activos (No eliminados)
        $activeSuppliersCount = Supplier::count();

        // 3. Éxito de Conexiones (Últimas 24 horas)
        $last24Hours = now()->subDay();
        $totalConnections = SupplierConnectionStatus::where('created_at', '>=', $last24Hours)->count();
        $successfulConnections = SupplierConnectionStatus::where('created_at', '>=', $last24Hours)
            ->where('status', 'completed')
            ->count();

        $connectionSuccessRate = $totalConnections > 0 
            ? round(($successfulConnections / $totalConnections) * 100, 1) 
            : 100;

        return [
            'total_debt' => (float)$totalDebt,
            'active_suppliers_count' => $activeSuppliersCount,
            'connection_success_rate' => $connectionSuccessRate,
            'successful_connections' => $successfulConnections,
            'total_connections_24h' => $totalConnections
        ];
    }

    /**
     * Obtiene los proveedores desactivados.
     */
    public function getDisabledSuppliers(Request $request)
    {
        $search = trim($request->query('search', ''));

        return Supplier::query()
            ->where('is_active', false)
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->with(['connections:id,supplier_id,type,last_connection'])
            ->orderBy('name', 'asc')
            ->get();
    }
}
