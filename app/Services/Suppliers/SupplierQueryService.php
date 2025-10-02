<?php

namespace App\Services\Suppliers;

use App\Models\AutoOrder;
use App\Models\Laboratory;
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
                $subDebt = DB::raw('(
                    SELECT COALESCE(SUM(i.total_amount), 0) - COALESCE(SUM(ip.amount), 0)
                    FROM invoices i
                    LEFT JOIN invoice_payment_invoice pivot ON pivot.invoice_id = i.id
                    LEFT JOIN invoice_payments ip ON ip.id = pivot.payment_id
                    WHERE i.supplier_id = suppliers.id
                    AND i.status IN ("loaded", "ordered")
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

            DB::transaction(function () use ($supplier, $uniqueProducts, $invoices) {
                $supplier->productSuppliers()->delete();
                foreach (array_chunk($uniqueProducts, 500) as $chunk) {
                    $supplier->productSuppliers()->createMany($chunk);
                }

                InvoiceDetail::whereIn("invoice_id", $supplier->invoices()->pluck("id"))->delete();
                $supplier->invoices()->delete();
                foreach ($invoices as $invoice) {
                    $header = $invoice['header'];
                    $lines = $invoice['lines'];

                    $invoiceModel = $supplier->invoices()->create([
                        ...Arr::only($header, Invoice::FILLABLEHEADER),
                        'uploaded_by' => auth()->id() ?? 1,
                        'registered_by' => auth()->id() ?? 1,
                    ]);

                    $details = collect($lines)->map(function ($line) use ($invoiceModel) {
                        return [
                            ...Arr::only($line, InvoiceDetail::FILLABLEDETAILS),
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
        $selectedSupplier = $filters["selectedSupplier"] ?? null;

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
            ->when($selectedSupplier, function ($query) use ($selectedSupplier) {
                $query->where("suppliers.id", $selectedSupplier);
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
                    "SELECT COALESCE(EXP(SUM(LN(1 - rate))), 1)
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
            \Log::error($e);
        }
    }

    public function getProducts(Request $request)
    {
        $laboratoryId = $request->query("laboratoryId");
        $supplierId = $request->query("supplierId");
        $perPage = $request->query("perPage", 10) ?? 10;
        $search = $request->query('q');
        $originId = $request->query("originId");
        $hasStock = $request->has('hasStock')
            ? filter_var($request->query("hasStock"), FILTER_VALIDATE_BOOLEAN) : null;
        $isStrictSearch = filter_var($request->query("isStrictSearch"), FILTER_VALIDATE_BOOLEAN);

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
                $searchTerm = "%{$search}%";

                if ($isStrictSearch) {
                    $query->where('product_suppliers.name', 'like', "%{$searchTerm}%")
                        ->orWhere('product_suppliers.active_ingredient', 'like', "%{$searchTerm}%")
                        ->orWhere('product_suppliers.barcode_match', 'like', $searchTerm)
                        ->orWhere('product_suppliers.id', 'like', $searchTerm);
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('product_suppliers.name', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.active_ingredient', 'like', "%{$word}%")
                                ->orWhere('product_suppliers.laboratory', 'like', "%{$word}%");
                        });
                    }
                }
            })
            ->when(!empty($originId), function ($query) use ($originId) {
                $query->where('products.origin_id', $originId);
            })
            ->when($supplierId, function ($query) use ($supplierId) {
                $query->where("supplier_id", $supplierId);
            })
            ->when($laboratoryId, function ($query) use ($laboratory) {
                $query->where("laboratory", $laboratory->name);
            })
            ->orderBy("name", "asc")
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
        $quantity = $request->quantity;
        $discount = $request->boolean("discount");
        $product = ProductSupplier::find($productId);

        $order = AutoOrder::orderByDesc("created_at")
            ->whereDate("created_at", now()->today())
            ->first();

        $unitCost = $discount ? $product->unit_cost_usd_with_discount : $product->unit_cost_usd;
        $subtotal = $unitCost * $quantity;

        if (isset($order)) {
            $order->details()->create([
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
                "product_suppliers_id" => $productId,
                "quantity" => $quantity,
                "unit_cost" => $unitCost,
                "subtotal" => $subtotal
            ]);
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
