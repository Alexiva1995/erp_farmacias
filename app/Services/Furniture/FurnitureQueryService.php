<?php

declare(strict_types=1);

namespace App\Services\Furniture;

use App\Models\Furniture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FurnitureQueryService
{
    /**
     * Calcula el valor total actual de todo el mobiliario
     */
    public function calculateTotalValue(): float
    {
        return (float) Furniture::sum('cost');
    }

    /**
     * Obtiene el detalle del valor de cada mobiliario
     */
    public function getDetailedValues(): array
    {
        $furniture = Furniture::select(['id', 'name', 'cost', 'acquisition_year', 'annual_depreciation_rate'])->get();
        
        return $furniture->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'original_cost' => (float) $item->cost,
                'current_value' => $item->getCurrentValue(),
                'acquisition_year' => (int) $item->acquisition_year,
                'depreciation_rate' => (float) $item->annual_depreciation_rate,
            ];
        })->toArray();
    }

    /**
     * Obtiene estadísticas del mobiliario
     */
    public function getFurnitureStats(): array
    {
        $furniture = Furniture::select(['id', 'cost', 'acquisition_year', 'annual_depreciation_rate'])->get();

        $totalOriginalCost = (float) $furniture->sum('cost');
        $totalCurrentValue = $this->calculateTotalValue();
        $totalDepreciation = $totalOriginalCost - $totalCurrentValue;

        return [
            'total_items' => $furniture->count(),
            'total_original_cost' => $totalOriginalCost,
            'total_current_value' => $totalCurrentValue,
            'total_depreciation' => $totalDepreciation,
            'depreciation_percentage' => $totalOriginalCost > 0 ? ($totalDepreciation / $totalOriginalCost) * 100 : 0,
        ];
    }

    /**
     * Construye la consulta filtrada y optimizada para el listado de mobiliario
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = Furniture::query()->select([
            'id',
            'name',
            'cost',
            'acquisition_year',
            'annual_depreciation_rate',
            'created_at',
            'updated_at'
        ]);

        // Búsqueda por término
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Filtro por año de adquisición
        if ($request->filled('acquisitionYear')) {
            $query->where('acquisition_year', (int) $request->input('acquisitionYear'));
        }

        // Filtro por rango de depreciación acumulada aproximada
        if ($request->filled('depreciationRange')) {
            $currentYear = (int) date('Y');
            $depreciationRange = $request->input('depreciationRange');

            switch ($depreciationRange) {
                case 'low':
                    $query->whereRaw("(({$currentYear} - acquisition_year) * annual_depreciation_rate) <= 20");
                    break;
                case 'medium':
                    $query->whereRaw("(({$currentYear} - acquisition_year) * annual_depreciation_rate) BETWEEN 21 AND 50");
                    break;
                case 'high':
                    $query->whereRaw("(({$currentYear} - acquisition_year) * annual_depreciation_rate) BETWEEN 51 AND 80");
                    break;
                case 'very_high':
                    $query->whereRaw("(({$currentYear} - acquisition_year) * annual_depreciation_rate) > 80");
                    break;
            }
        }

        // Filtro por fecha de creación
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->input('endDate'));
        }

        // Ordenamiento
        $allowedSorts = ['id', 'name', 'cost', 'acquisition_year', 'annual_depreciation_rate', 'created_at'];
        if ($request->filled('sortBy') && in_array($request->input('sortBy'), $allowedSorts, true)) {
            $sortBy = $request->input('sortBy');
            $orderBy = strtolower($request->input('orderBy')) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $orderBy);
        } elseif ($request->input('sortBy') === 'current_value') {
            $currentYear = (int) date('Y');
            $orderBy = strtolower($request->input('orderBy')) === 'asc' ? 'asc' : 'desc';
            $query->orderByRaw("cost * GREATEST(0, 1 - (({$currentYear} - acquisition_year) * annual_depreciation_rate / 100)) {$orderBy}");
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Calcula la depreciación acumulada total del mobiliario
     */
    public function calculateTotalDepreciation(): float
    {
        $currentYear = (int) date('Y');

        $depreciation = Furniture::query()
            ->selectRaw("
                SUM(
                    cost * LEAST(1.0, GREATEST(0, ({$currentYear} - acquisition_year)) * (annual_depreciation_rate / 100.0))
                ) as total_depreciation
            ")
            ->value('total_depreciation');

        return (float) round((float) ($depreciation ?? 0), 2);
    }
}
