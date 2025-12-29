<?php

namespace App\Services\Expirations;

use App\Models\DonativeLog;
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
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $isStrictSearch = filter_var($request->get('isStrictSearch', false), FILTER_VALIDATE_BOOLEAN);
            
            if ($isStrictSearch) {
                $searchPattern = "%{$searchTerm}%";
                $query->where(function ($subQuery) use ($searchPattern) {
                    $subQuery->where('lot_number', 'like', $searchPattern)
                        ->orWhereHas('product', function ($productQuery) use ($searchPattern) {
                            $productQuery->where('name', 'like', $searchPattern)
                                ->orWhere('active_ingredient', 'like', $searchPattern)
                                ->orWhere('barcode', 'like', $searchPattern)
                                ->orWhere('id', 'like', $searchPattern);
                        });
                });
            } else {
                $words = explode(' ', trim($searchTerm));
                $query->where(function ($subQuery) use ($words) {
                    foreach ($words as $word) {
                        $wordPattern = "%{$word}%";
                        $subQuery->where(function ($wordQuery) use ($wordPattern) {
                            $wordQuery->where('lot_number', 'like', $wordPattern)
                                ->orWhereHas('product', function ($productQuery) use ($wordPattern) {
                                    $productQuery->where('name', 'like', $wordPattern)
                                        ->orWhere('active_ingredient', 'like', $wordPattern)
                                        ->orWhere('barcode', 'like', $wordPattern)
                                        ->orWhere('id', 'like', $wordPattern)
                                        ->orWhereHas('laboratory', function ($labQuery) use ($wordPattern) {
                                            $labQuery->where('name', 'like', $wordPattern);
                                        });
                                });
                        });
                    }
                });
            }
        }

        if ($request->filled('laboratory_id')) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('laboratory_id', $request->laboratory_id);
            });
        }

        if ($request->filled('start_date')) {
            $query->where('expiration_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('expiration_date', '<=', $request->end_date);
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta.
     */
    private function applySorting(Builder $query, Request $request): Builder
    {
        if ($request->filled('sortBy') && $request->sortBy === 'product.name') {
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
        $query = ExpiredLog::with('product.laboratory', 'donativeLog');

        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('product_name', 'like', $searchTerm)
                    ->orWhere('lot_number', 'like', 'searchTerm');
            });
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    public function getExpiredLotsSummary()
    {
        $summaries = DB::table('expired_logs')
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total_products'),
                DB::raw('SUM(total_lost_value) as total_cost')
            ])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month', 'desc')
            ->get();

        foreach ($summaries as $summary) {
            $summary->donation_count = DB::table('donations')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$summary->month])
                ->count();

            $summary->has_price_adjustment = DB::table('price_adjustment_logs')
                ->where('month', $summary->month)
                ->exists();
        }

        return $summaries;
    }

}
