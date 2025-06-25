<?php

namespace App\Services\Expirations;

use App\Models\ExpiredLog;
use App\Models\ProductLot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public function getExpiredLotsLogQuery(Request $request): Builder
    {
        $query = ExpiredLog::with('product.laboratory')->whereDoesntHave('donativeLog');

        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('product_name', 'like', $searchTerm)
                    ->orWhere('lot_number', 'like', $searchTerm);
            });
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        } else {
            // Un orden por defecto consistente.
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * 
     *
     * @return object
     */
    public function getExpiredLotsSummary(): Collection
    {
        $dateFormat = '';
        $dbDriver = DB::connection()->getDriverName();

        if ($dbDriver === 'mysql') {
            $dateFormat = "DATE_FORMAT(created_at, '%Y-%m')";
        } elseif ($dbDriver === 'sqlite') {
            $dateFormat = "strftime('%Y-%m', created_at)";
        } elseif ($dbDriver === 'pgsql') {
            $dateFormat = "TO_CHAR(created_at, 'YYYY-MM')";
        } else {
            $dateFormat = "created_at";
        }

        $summaries = ExpiredLog::whereDoesntHave('donativeLog')
            ->select(
                DB::raw("$dateFormat as month"),
                DB::raw('SUM(expired_quantity) as total_quantity'),
                DB::raw('SUM(total_lost_value) as total_lost_value')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();

        return $summaries;
    }
}
