<?php

namespace App\Services\History;

use App\Models\FiscalHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoryQueryService
{
    /**
     * Prepara la consulta base para FiscarHistory.
     */
    private function getBaseQuery(): Builder
    {
        return FiscalHistory::with(['user', 'details']);
    }

    /**
     * Aplica los filtros a la consulta de productos.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('id', 'like', $searchTerm)
                    ->orWhere('invoice_number', 'like', $searchTerm)
                    ->orWhere('business_name', 'like', $searchTerm);
            });
        }

        // Filtro por fecha de factura
        if (!empty($filters['startDate'])) {
            $query->whereDate('invoice_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('invoice_date', '<=', $filters['endDate']);
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de productos.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        // Columnas válidas para ordenar
        $validSortColumns = [
            'id',
            'invoice_number',
            'business_name',
            'invoice_date',
            'exempt_amount',
            'iva_amount',
            'total_amount',
        ];

        if (in_array($sortBy, $validSortColumns)) {
            return $query->orderBy($sortBy, $orderBy);
        }

        // Orden predeterminado
        return $query->orderBy('invoice_date', 'desc');
    }

    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
