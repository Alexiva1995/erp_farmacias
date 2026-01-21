<?php

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

class SupplierQueryService
{
    /**
     * Prepares the base query for suppliers.
     */
    private function getBaseQuery(): Builder
    {

        return Supplier::query()
            ->withoutTrashed()
            ->select('suppliers.*')
            ->with(['latestScore', 'paymentRules', 'paymentDate']);
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

        return $query;
    }

    /**
     * Applies sorting to the supplier query.
     */
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
                /*$subDebt = DB::raw('(
                    SELECT COALESCE(SUM(i.total_amount), 0) - COALESCE(SUM(ip.amount), 0)
                    FROM invoices i
                    LEFT JOIN invoice_payment_invoice pivot ON pivot.invoice_id = i.id
                    LEFT JOIN invoice_payments ip ON ip.id = pivot.payment_id
                    WHERE i.supplier_id = suppliers.id
                    AND i.status IN ("loaded", "ordered")
                )');*/
                $subDebt = DB::raw('(
                    SELECT SUM(COALESCE(i.Total_usd, 0)) 
                    FROM invoices i
                    WHERE i.supplier_id = suppliers.id
                    AND i.status_payment = 0  
                )');
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
        try {
            $products = $data["products"] ?? [];
            $invoices = $data["invoices"] ?? [];

            $uniqueProducts = collect($products)
                ->groupBy(
                    fn($row) => is_null($row["product_id"])
                    ? Str::uuid()
                    : $row["product_id"] . "-" . $row["supplier_id"],
                )
                ->map(fn($group) => $group->sortBy("unit_cost")->first())
                ->values()
                ->toArray();

            $existingInvoiceNumbers = Invoice::whereIn(
                'invoice_number',
                collect($invoices)->pluck('header.invoice_number')->filter()->unique()
            )->pluck('invoice_number')->toArray();

            $filteredInvoices = collect($invoices)
                ->filter(function ($invoice) use ($existingInvoiceNumbers) {
                    $number = $invoice['header']['invoice_number'] ?? null;
                    return $number && !in_array($number, $existingInvoiceNumbers);
                })->values()->toArray();

            DB::transaction(function () use ($supplier, $uniqueProducts, $filteredInvoices) {
                // Identificar product_suppliers que están en auto_orders (NO borrarlos)
                // Mapear por product_id para facilitar la búsqueda
                $productSuppliersInAutoOrdersCollection = DB::table('product_suppliers')
                    ->join('auto_order_details', 'product_suppliers.id', '=', 'auto_order_details.product_suppliers_id')
                    ->where('product_suppliers.supplier_id', $supplier->id)
                    ->select('product_suppliers.id', 'product_suppliers.product_id')
                    ->distinct()
                    ->get();

                $productSuppliersInAutoOrders = $productSuppliersInAutoOrdersCollection->keyBy('product_id');

                // Obtener los IDs de product_suppliers que están en auto_orders (para no borrarlos)
                $existingProductSupplierIds = $productSuppliersInAutoOrdersCollection->pluck('id')->toArray();

                // Mapear productos del FTP por product_id
                $uniqueProductsCollection = collect($uniqueProducts);

                // Separar productos con ID (para actualizaciones potenciales) y sin ID (siempre inserciones nuevas)
                $productsWithId = $uniqueProductsCollection->whereNotNull('product_id');
                $productsWithoutId = $uniqueProductsCollection->whereNull('product_id')->values()->toArray();

                $ftpProductsMap = $productsWithId
                    ->keyBy('product_id')
                    ->toArray();

                // Obtener todos los product_suppliers existentes para actualizar los que están en auto_orders
                $existingProductSuppliers = DB::table('product_suppliers')
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('id', $existingProductSupplierIds)
                    ->get()
                    ->keyBy('product_id');

                // Actualizar los product_suppliers que están en auto_orders
                foreach ($productSuppliersInAutoOrders as $productId => $productSupplierData) {
                    if (isset($ftpProductsMap[$productId]) && isset($existingProductSuppliers[$productId])) {
                        $ftpProduct = $ftpProductsMap[$productId];
                        $existingProductSupplier = $existingProductSuppliers[$productId];

                        ProductSupplier::where('id', $productSupplierData->id)->update([
                            'barcode_match' => $ftpProduct['barcode_match'] ?? $existingProductSupplier->barcode_match,
                            'name' => $ftpProduct['name'] ?? $existingProductSupplier->name,
                            'laboratory' => $ftpProduct['laboratory'] ?? $existingProductSupplier->laboratory,
                            'expiration' => $ftpProduct['expiration'] ?? $existingProductSupplier->expiration,
                            'unit_cost' => $ftpProduct['unit_cost'] ?? $existingProductSupplier->unit_cost,
                            'unit_cost_usd' => $ftpProduct['unit_cost_usd'] ?? $existingProductSupplier->unit_cost_usd,
                            'connection_date' => now()->toDateString(),
                            'cod_supplier' => $ftpProduct['cod_supplier'] ?? $existingProductSupplier->cod_supplier,
                            'quantity' => $ftpProduct['quantity'] ?? $existingProductSupplier->quantity,
                            'active_ingredient' => $ftpProduct['active_ingredient'] ?? $existingProductSupplier->active_ingredient,
                        ]);
                        // Eliminar del mapa para no procesarlo como nuevo
                        unset($ftpProductsMap[$productId]);
                        //}
                    }
                }

                // Eliminar solo los product_suppliers que NO están en auto_orders
                $productSuppliersToDelete = DB::table('product_suppliers')
                    ->where('supplier_id', $supplier->id)
                    ->whereNotIn('id', $existingProductSupplierIds)
                    ->pluck('id');

                ProductSupplier::whereIn('id', $productSuppliersToDelete)->delete();

                // Crear los productos nuevos del FTP (los que no están en auto_orders + los que no tienen ID)
                $newProducts = array_merge(array_values($ftpProductsMap), $productsWithoutId);

                foreach ($newProducts as $productData) {
                    try {
                        ProductSupplier::updateOrCreate(
                            [
                                'supplier_id' => $supplier->id,
                                'cod_supplier' => $productData['cod_supplier'] ?? null,
                                'product_id' => $productData['product_id'] ?? null,
                            ],
                            $productData
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Si el error es de duplicado (23000), lo logueamos y seguimos con el siguiente
                        if ($e->getCode() == 23000) {
                            Log::warning("Producto duplicado ignorado en el servidor: " . ($productData['cod_supplier'] ?? 'Sin código'));
                            continue;
                        }
                        throw $e;
                    }
                }

                //InvoiceDetail::whereIn("invoice_id", $supplier->invoices()->pluck("id"))->delete();
                //$supplier->invoices()->delete();
                foreach ($filteredInvoices as $invoice) {
                    $header = $invoice['header'];
                    $lines = $invoice['lines'];

                    $invoiceModel = $supplier->invoices()->create([
                        ...Arr::only($header, Invoice::FILLABLEHEADER),
                        'status' => $invoice['status'] ?? 'pending',
                        'uploaded_by' => auth()->id() ?? 1,
                        'registered_by' => auth()->id() ?? 1,
                    ]);

                    // ✅ Obtener exchange_rate del header
                    $exchangeRate = floatval($header['exchange_rate'] ?? 1);
                    $isVitaclinics = $supplier->id === 15;

                    $details = collect($lines)->map(function ($line) use ($invoiceModel, $exchangeRate, $isVitaclinics) {
                        $lineData = Arr::only($line, InvoiceDetail::FILLABLEDETAILS);

                        // ✅ Si es Vitaclinics y tiene exchange_rate, multiplicar unit_cost
                        if ($isVitaclinics && $exchangeRate > 1) {
                            $unitCost = floatval($lineData['unit_cost'] ?? 0);
                            $lineData['unit_cost'] = number_format($unitCost * $exchangeRate, 2, '.', '');

                            // Recalcular total_cost también
                            $quantity = floatval($lineData['quantity'] ?? 0);
                            $lineData['total_cost'] = number_format($lineData['unit_cost'] * $quantity, 2, '.', '');
                        }

                        return [
                            ...$lineData,
                            'invoice_id' => $invoiceModel->id,
                        ];
                    })->toArray();

                    $invoiceModel->details()->createMany($details);
                }
            });
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    public function getSupplierConnections(Request $request)
    {
        $filters = $request->query();
        $perPage = $filters["perPage"] ?? 10;

        // Buscamos el parámetro 'search' (enviado desde el frontend)
        // O mantenemos compatibilidad si enviasen 'selectedSupplier' como texto
        $searchTerm = $filters["search"] ?? $filters["selectedSupplier"] ?? null;

        $paginated = DB::table("suppliers")
            ->select(
                "suppliers.name as name",
                "suppliers.id",
                DB::raw(
                    "COALESCE(supplier_connections.last_connection, 'No se ha establecido conexión') as last_connection",
                ),
                DB::raw("UPPER(COALESCE(CASE WHEN supplier_connections.type = 'file' THEN 'Archivo Excel' ELSE supplier_connections.type END, 'No registrado')) as type"),
            )
            ->leftJoin("supplier_connections", "supplier_id", "=", "suppliers.id")
            ->when($searchTerm, function ($query) use ($searchTerm) {
                // Buscamos coincidencia parcial en el nombre
                $query->where("suppliers.name", "LIKE", "%{$searchTerm}%");
            })
            ->paginate($perPage);

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

        $search = trim($request->query('q'));

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

        $laboratory = Laboratory::where("id", $laboratoryId)->first();

        $results = ProductSupplier::query()
            ->select([
                "product_suppliers.id as id",
                DB::raw("product_suppliers.name as name"),
                "suppliers.name as supplier_name",
                "product_suppliers.unit_cost as unit_cost_bs",
                "product_suppliers.unit_cost_usd as unit_cost_usd",
                DB::raw("COALESCE(product_suppliers.unit_cost_with_discount, 0) as final_cost_bs"),
                DB::raw("COALESCE(product_suppliers.unit_cost_usd_with_discount, 0) as final_cost_usd"),
                "product_suppliers.expiration as expiration",
                "product_suppliers.active_ingredient as active_ingredient"
            ])
            ->leftJoin("products", "products.id", "=", "product_suppliers.product_id")
            ->leftJoin("suppliers", "suppliers.id", "=", "product_suppliers.supplier_id")

            ->when(!empty($search), function ($query) use ($search, $isStrictSearch) {

                if ($isStrictSearch) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_suppliers.name', '=', $search)
                            ->orWhere('product_suppliers.id', '=', $search)
                            ->orWhere('product_suppliers.barcode_match', '=', $search);
                    });
                } else {
                    $words = explode(' ', $search);
                    foreach ($words as $word) {
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('product_suppliers.name', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.active_ingredient', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.laboratory', 'like', "%{$word}%");
                        });
                    }
                }
            })
            // -------------------------------------

            ->when(!empty($originId), function ($query) use ($originId) {
                $query->where('products.origin_id', $originId);
            })
            ->when($supplierId, function ($query) use ($supplierId) {
                $query->where("supplier_id", $supplierId);
            })
            ->when($laboratoryId, function ($query) use ($laboratory) {
                $query->where("laboratory", $laboratory->name);
            })

            ->orderBy($sortColumn, $sortOrder)

            ->when($hasStock !== null, function ($query) use ($hasStock) {
                $stockSql = 'COALESCE((SELECT SUM(pl.quantity)
                                    FROM product_lots pl
                                    WHERE pl.product_id = products.id
                                      AND pl.expiration_date >= CURDATE()
                                      AND pl.quantity > 0), 0)';

                $query->havingRaw($hasStock ? "{$stockSql} > 0"
                    : "{$stockSql} = 0");
            })
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

    public function addProductToOrder(StoreProductIntoautoOrderRequest $request)
    {
        $productId = $request->productId;
        $mainProductId = $request->main_product_id;
        $quantity = $request->quantity;
        $discount = $request->boolean("discount");
        $product = ProductSupplier::find($productId);
        $mainProduct = Product::find($mainProductId);

        $mainProduct = $mainProductId ? Product::find($mainProductId) : null;
        if ($mainProduct && empty($mainProduct->barcode) && !empty($product->barcode_match)) {
            $mainProduct->update([
                'barcode' => $product->barcode_match
            ]);
        }

        $order = AutoOrder::where('supplier_id', $product->supplier_id)
            ->whereDate("created_at", now()->today())
            ->orderByDesc("created_at")
            ->first();

        $unitCost = $discount ? $product->unit_cost_usd_with_discount : $product->unit_cost_usd;
        $subtotal = $unitCost * $quantity;

        $detailPayload = [
            "product_id" => $mainProduct ? $mainProduct->id : null,
            "product_suppliers_id" => $productId,
            "quantity" => $quantity,
            "unit_cost" => $unitCost,
            "subtotal" => $subtotal
        ];


        if (isset($order)) {
            $order->details()->create($detailPayload);

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
            $mainProduct->update(['is_ordered' => false]);
        }
        $product->decrement("quantity", $quantity);

        return true;
    }

    public function deleteProducts(Supplier $supplier)
    {
        $supplier->productSuppliers()->delete();

        return response()->json(["status" => "ok"]);
    }

    public function getRecentConnectionStatusesForUser(int $userId, int $minutes = 10): Collection
    {
        return SupplierConnectionStatus::with('supplier')
            ->where('user_id', $userId)
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
}
