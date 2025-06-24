<?php

namespace App\Services\Expirations;

use App\Models\ProductLot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExpirationQueryService
{
    /**
     * Prepara la consulta base para los lotes que están por expirar.
     */
    private function getBaseQuery(): Builder
    {
        $today = now()->startOfDay();
        $expirationCutoffDate = now()->addMonths(6)->endOfDay();

        return ProductLot::with([
            'product' => fn($query) => $query->with(['laboratory', 'origin', 'category'])
        ])
            ->where('quantity', '>', 0)
            ->whereBetween('expiration_date', [$today, $expirationCutoffDate]);
    }

    /**
     * Aplica filtros de búsqueda a la consulta.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $query->whereHas('product', function ($productQuery) use ($searchTerm) {
                $productQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm);
            });
        }
        return $query;
    }

    /**
     * Aplica la ordenación a la consulta.
     */
    private function applySorting(Builder $query, Request $request): Builder
    {
        if ($request->filled('sortBy') && $request->sortBy === 'name') {
            return $query->join('products', 'product_lots.product_id', '=', 'products.id')
                ->orderBy('products.name', $request->input('orderBy', 'asc'))
                ->select('product_lots.*');
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            return $query->orderBy($request->sortBy, $request->orderBy);
        }

        return $query->orderBy('expiration_date', 'asc');
    }

    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getExpiringLotsQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query;
    }
}
