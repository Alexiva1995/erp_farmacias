<?php

namespace App\Services\ProductLots;

use App\Models\ProductLot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductLotQueryService
{
    /**
     * Prepara la consulta base para los lotes de productos.
     */
    private function getBaseQuery(): Builder
    {
        return ProductLot::query()
            ->select('product_lots.*')
            ->with([
                'product' => function ($query) {
                    $query->select('id', 'name', 'laboratory_id')
                        ->with('laboratory:id,name');
                },
                'supplier:id,name'
            ]);
    }

    /**
     * Aplica los filtros a la consulta de lotes de productos.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['search'])) {
            $searchTerm = "%{$filters['search']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('product_lots.lot_number', 'like', $searchTerm)
                    ->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('supplier', function ($supplierQuery) use ($searchTerm) {
                        $supplierQuery->where('name', 'like', $searchTerm);
                    });
            });
        }

        if (!empty($filters['laboratory'])) {
            $query->whereHas('product', function ($productQuery) use ($filters) {
                $productQuery->where('laboratory_id', $filters['laboratory']);
            });
        }

        $stockStatus = $filters['stockStatus'] ?? null;
        if ($stockStatus !== null) {
            if ($stockStatus === true) {
                $query->where('quantity', '>', 0);
            } elseif ($stockStatus === false) {
                $query->where('quantity', '=', 0);
            }
        }

        if (!empty($filters['startDate'])) {
            $query->where('expiration_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->where('expiration_date', '<=', $filters['endDate']);
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de lotes de productos.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('product_lots.id', 'desc');
        }

        switch ($sortBy) {
            case 'product.name':
                return $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.name', $orderBy);

            case 'supplier.name':
                return $query->join('suppliers', 'product_lots.supplier_id', '=', 'suppliers.id')
                    ->orderBy('suppliers.name', $orderBy);

            case 'laboratory.name':
                return $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'valid_stock':
                $subQuery = DB::raw('CASE WHEN product_lots.expiration_date >= CURDATE() THEN product_lots.quantity ELSE 0 END');
                return $query->orderBy($subQuery, $orderBy);

            case 'next_expiration':
                return $query->orderBy('product_lots.expiration_date', $orderBy);

            case 'sale_price':
                return $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.sale_price', $orderBy);

            case 'most_sold':
                $subQuery = DB::raw('COALESCE((SELECT SUM(order_details.quantity) FROM order_details WHERE order_details.product_id = product_lots.product_id), 0)');
                return $query->orderBy($subQuery, $orderBy);

            default:
                return $query->orderBy($sortBy, $orderBy);
        }
    }

    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'search' => $request->search,
            'laboratory' => $request->laboratory,
            'stockStatus' => $request->has('stockStatus') ? filter_var($request->stockStatus, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }
}
