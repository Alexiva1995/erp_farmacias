<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
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
                        ->with(['latestScore', 'paymentRules']);
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
                    ->orWhere(
                        "suppliers.collections_phone",
                        "like",
                        $searchTerm,
                    )
                    ->orWhere("suppliers.id", "like", $searchTerm);
            });
        }

        return $query;
    }

    /**
     * Applies sorting to the supplier query.
     */
    private function applySorting(
        Builder $query,
        ?string $sortBy,
        string $orderBy,
    ): Builder {
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
        $this->applySorting(
            $query,
            $request->input("sortBy"),
            $request->input("orderBy", "asc"),
        );

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
    public function getUnpaidInvoicesByDate(
        Supplier $supplier,
    ): SupportCollection {
        return Invoice::query()
            ->where("supplier_id", $supplier->id)
            ->whereHas("payments", fn($q) => $q->where("status", "unpaid"))
            ->with(["payments" => fn($q) => $q->where("status", "unpaid")])
            ->get()
            ->flatMap(function ($invoice) {
                return $invoice->payments->map(function ($payment) use (
                    $invoice,
                ) {
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
            $products = $data['products'] ?? [];
            $invoices = $data['invoices'] ?? [];

            $uniqueProducts = collect($products)
                ->groupBy(fn($row) => is_null($row["product_id"])
                        ? Str::uuid()
                        : $row["product_id"] . "-" . $row["supplier_id"])
                ->map(fn ($group) => $group->sortBy("unit_cost")->first())
                ->values()
                ->toArray();

            DB::transaction(function () use ($supplier, $uniqueProducts, $invoices) {
                // Guardar productos
                $supplier->productSuppliers()->delete();
                foreach (array_chunk($uniqueProducts, 500) as $chunk) {
                    $supplier->productSuppliers()->createMany($chunk);
                }

                // Guardar facturas
                InvoiceDetail::whereIn('invoice_id', $supplier->invoices()->pluck('id'))->delete();
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

    public function getRecentConnectionStatusesForUser(int $userId, int $minutes = 10): Collection
    {
        return SupplierConnectionStatus::with('supplier')
            ->where('user_id', $userId)
            ->whereIn('status', ['completed', 'failed'])
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->latest()
            ->get();
    }
}
