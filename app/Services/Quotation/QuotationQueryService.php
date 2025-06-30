<?php

namespace App\Services\Quotation;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationQueryService
{

    private function getBaseQuery(): Builder
    {
        return Product::query()->select(
            'products.*'
            )->with([
            'laboratory',
            'origin',
        ])->withSum('lots', 'quantity');
    }


    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm);
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }

        $hasStock = $filters['hasStock'] ?? null;

        if ($hasStock === false) {
            $query->whereDoesntHave('lots', function ($lotQuery) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay());
            });
        } elseif ($hasStock === true) {
            $query->whereHas('lots', function ($lotQuery){
               $lotQuery->where('expiration_date', '>=', now()->startOfDay());
            });
        }

        return $query;
    }

        private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('products.name', 'asc');
        }

        switch ($sortBy) {
            case 'laboratory.name':
                return $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'valid_stock':
                $subQuery = DB::raw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE()), 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'next_expiration':
                $subQuery = DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE())');
                return $query->orderBy($subQuery, $orderBy);

            case 'lots_sum_quantity':
                return $query->orderBy('lots_sum_quantity', $orderBy);

            case 'id':
            case 'name':
            case 'cost_price':
            case 'sale_price':
                return $query->orderBy("products.{$sortBy}", $orderBy);
        }

        return $query;
    }

       public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }


}
