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


            $invoiceNumbersToFilter = collect($invoices)->pluck('header.invoice_number')->filter()->unique()->toArray();
            
            // Expandir números posibles (ej. 43141520 y A43141520) para evitar duplicar facturas de Dronena
            $allPossibleNumbers = [];
            foreach ($invoiceNumbersToFilter as $num) {
                $clean = ltrim((string)$num, 'A');
                $allPossibleNumbers[] = $num;
                $allPossibleNumbers[] = $clean;
                $allPossibleNumbers[] = 'A' . $clean;
            }
            $allPossibleNumbers = array_unique($allPossibleNumbers);

            $existingInvoices = Invoice::whereIn('invoice_number', $allPossibleNumbers)
                ->pluck('invoice_number')
                ->map(fn($n) => ltrim((string)$n, 'A'))
                ->toArray();

            $filteredInvoices = collect($invoices)
                ->filter(function ($invoice) use ($existingInvoices) {
                    $number = $invoice['header']['invoice_number'] ?? null;
                    if (!$number) return false;
                    $cleanNumber = ltrim((string)$number, 'A');
                    $isNew = !in_array($cleanNumber, $existingInvoices);
                    if (!$isNew) {
                        Log::warning("Factura filtrada (ya existe en ERP con o sin 'A')", ['number' => $number]);
                    }
                    return $isNew;
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

            $allowedColumns = [
                'product_id', 'supplier_id', 'barcode_match', 'name', 'laboratory', 
                'expiration', 'unit_cost', 'unit_cost_usd', 'connection_date', 
                'quantity', 'unit_cost_with_discount', 'unit_cost_usd_with_discount', 
                'cod_supplier', 'active_ingredient', 'created_at', 'updated_at'
            ];
            $allowedSet = array_flip($allowedColumns);
            $nowStr = now()->toDateTimeString();
            $todayStr = now()->toDateString();

            $batchRows = [];
            foreach ($uniqueProducts as $productData) {
                if (!isset($productData['supplier_id'])) {
                    $productData['supplier_id'] = $supplier->id;
                }
                if (!isset($productData['connection_date'])) {
                    $productData['connection_date'] = $todayStr;
                }
                if (!isset($productData['unit_cost'])) {
                    $productData['unit_cost'] = 0;
                }
                if (!isset($productData['unit_cost_usd'])) {
                    $productData['unit_cost_usd'] = 0;
                }
                if (!isset($productData['created_at'])) {
                    $productData['created_at'] = $nowStr;
                }
                if (!isset($productData['updated_at'])) {
                    $productData['updated_at'] = $nowStr;
                }

                if (!isset($productData['quantity']) || empty($productData['quantity']) || $productData['quantity'] <= 0) {
                    $productData['quantity'] = 1000;
                }

                $batchRows[] = array_intersect_key($productData, $allowedSet);
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


            // Procesar facturas en su propia transacción
            DB::transaction(function () use ($supplier, $filteredInvoices) {
                foreach ($filteredInvoices as $invoice) {
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
                            } elseif (!empty($line['name'])) {
                                // 🆕 Crear producto en modo borrador (no visible hasta finalizar factura)
                                $product = Product::create([
                                    'name'       => $line['name'],
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
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error("Error in storeSupplierConnectionData: " . $e->getMessage());
            report($e);
            return false;
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
                DB::raw("COALESCE(product_suppliers.laboratory, 'N/A') as laboratory"),
                "product_suppliers.id",
                "product_suppliers.unit_cost",
                "product_suppliers.unit_cost_usd",
                DB::raw("COALESCE(products.name, 'N/A') as name"),
            )
            ->leftJoin("products", "products.id", "=", "product_suppliers.product_id")
            ->where("product_suppliers.supplier_id", "=", $supplier->id)
            ->where("product_suppliers.created_at", ">=", now()->subDays(7))
            ->orderByRaw("CASE WHEN COALESCE(products.name, 'N/A') = 'N/A' THEN 1 ELSE 0 END")
            ->orderBy("name", "asc")
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

        $sortableColumns = [
            'name' => 'product_suppliers.name',
            'unit_cost_bs' => 'product_suppliers.unit_cost',
            'unit_cost_usd' => 'product_suppliers.unit_cost_usd',
            'final_cost_bs' => 'final_cost_bs',
            'final_cost_usd' => 'final_cost_usd',
            'expiration' => 'product_suppliers.expiration'
        ];

        $sortColumn = $sortableColumns[$sortBy] ?? 'product_suppliers.name';

        $results = ProductSupplier::query()
            ->where('product_suppliers.created_at', '>=', now()->subDays(7))
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
     * Obtiene los proveedores que tienen productos subidos en product_suppliers.
     */
    public function getAvailableSuppliers(): Collection
    {
        return Supplier::query()
            ->whereHas('productSuppliers')
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

    public function getRecentConnectionStatusesForUser(int $userId, int $minutes = 10): Collection
    {
        return SupplierConnectionStatus::with('supplier')
            ->whereIn('status', ['completed', 'failed'])
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->latest()
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
