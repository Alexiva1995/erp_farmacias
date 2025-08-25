<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductQueryService
{
    /**
     * Prepara la consulta base para los productos.
     */
    private function getBaseQuery(): Builder
    {
        return Product::query()->select('products.*')->with([
            'category',
            'laboratory',
            'origin',
            'group',
            'profitability',
            'lots' => function ($query) {
                $query->where('quantity', '>', 0);
            },
        ]);
    }

    /**
     * Aplica los filtros a la consulta de productos.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm)
                    ->orWhere('id', 'like', $searchTerm);
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['is_psychotropic'])) {
            $query->where('psychotropic', $filters['is_psychotropic']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }
        // filtro de profitability is_locked
        if (!empty($filters['lockedValue'])) {
            $query->whereHas('profitability', function ($query) use ($filters) {
                $query->where("is_locked", $filters['lockedValue']);
            });
        }

        $hasStock = $filters['hasStock'] ?? null;

        if ($hasStock === false) {
            $query->whereDoesntHave('lots', function ($lotQuery) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay())
                    ->where('quantity', '>', 0);
            });
        } elseif ($hasStock === true || !empty($filters['startDate']) || !empty($filters['endDate'])) {
            $query->whereHas('lots', function ($lotQuery) use ($filters, $hasStock) {
                $lotQuery->where('quantity', '>', 0);

                if ($hasStock === true) {
                    $lotQuery->where('expiration_date', '>=', now()->startOfDay());
                }
                if (!empty($filters['startDate'])) {
                    $lotQuery->where('expiration_date', '>=', $filters['startDate']);
                }
                if (!empty($filters['endDate'])) {
                    $lotQuery->where('expiration_date', '<=', $filters['endDate']);
                }
            });
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de productos.
     */
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

            case 'most_sold':
                $subQuery = DB::raw('COALESCE((SELECT SUM(order_details.quantity) FROM order_details WHERE order_details.product_id = products.id), 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'id':
            case 'name':
            case 'unit_cost':
            case 'sale_price':
                return $query->orderBy("products.{$sortBy}", $orderBy);
        }

        return $query;
    }

    public function searchBarcodeProduct(Request $request)
    {
        $product = Product::where('barcode', $request->barcode)
            ->with(['laboratory', 'origin', 'category'])
            ->first();
        return $product;
    }
    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'groupId' => $request->groupId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'lockedValue' => $request->lockedValue, // <- para el filtro de profitability.is_locked
            'is_psychotropic' => $request->is_psychotropic,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
