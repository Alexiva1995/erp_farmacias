<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                        ->with(['latestScore'])
                        ->with(['paymentRule']);
    }

    /**
     * Applies filters to the supplier query.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('suppliers.name', 'like', $searchTerm)
                    ->orWhere('suppliers.sales_phone', 'like', $searchTerm)
                    ->orWhere('suppliers.collections_phone', 'like', $searchTerm)
                    ->orWhere('suppliers.id', 'like', $searchTerm);
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
            return $query->orderBy('suppliers.name', 'asc');
        }

        switch ($sortBy) {
            case 'latestScore.score':
                return $query
                    ->leftJoin('supplier_scores as ss', function ($join) {
                        $join->on('ss.supplier_id', '=', 'suppliers.id');
                    })
                    ->orderBy('ss.score', $orderBy)
                    ->orderBy('ss.evaluated_on', 'desc')
                    ->select('suppliers.*');

            case 'debt':
                $subDebt = DB::raw('(
                    SELECT COALESCE(SUM(i.total_amount), 0) - COALESCE(SUM(ip.amount), 0)
                    FROM invoices i
                    LEFT JOIN invoice_payment_invoice pivot ON pivot.invoice_id = i.id
                    LEFT JOIN invoice_payments ip ON ip.id = pivot.payment_id
                    WHERE i.supplier_id = suppliers.id
                    AND i.status IN ("loaded", "ordered")
                )');
                return $query->orderBy($subDebt, $orderBy);

            case 'id':
            case 'name':             
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
            'q' => $request->q
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
