<?php

declare(strict_types=1);

namespace App\Services\Furniture;

use App\Models\Furniture;

class FurnitureQueryService
{
    /**
     * Calcula el valor total actual de todo el mobiliario
     * considerando la depreciación por años
     * 
     * @return float
     */
    public function calculateTotalValue(): float
    {
        $furniture = Furniture::all();
        return (float) $furniture->sum('cost');
    }

    /**
     * Obtiene el detalle del valor de cada mobiliario
     * 
     * @return array
     */
    public function getDetailedValues(): array
    {
        $furniture = Furniture::all();
        $details = [];

        foreach ($furniture as $item) {
            $details[] = [
                'id' => $item->id,
                'name' => $item->name,
                'original_cost' => $item->cost,
                'current_value' => $item->getCurrentValue(),
                'acquisition_year' => $item->acquisition_year,
                'depreciation_rate' => $item->annual_depreciation_rate,
            ];
        }

        return $details;
    }

    /**
     * Obtiene estadísticas del mobiliario
     * 
     * @return array
     */
    public function getFurnitureStats(): array
    {
        $furniture = Furniture::all();

        $totalOriginalCost = $furniture->sum('cost');
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
    public function getFilteredQuery($request)
    {
        $query = Furniture::query();

        // Filtro por búsqueda de nombre
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Filtro por año de adquisición
        if ($request->filled('acquisitionYear')) {
            $query->where('acquisition_year', $request->input('acquisitionYear'));
        }

        // Filtro por rango de depreciación
        if ($request->filled('depreciationRange')) {
            $depreciationRange = $request->input('depreciationRange');

            switch ($depreciationRange) {
                case 'low':
                    $query->whereRaw('((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate) <= 20');
                    break;
                case 'medium':
                    $query->whereRaw('((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate) BETWEEN 21 AND 50');
                    break;
                case 'high':
                    $query->whereRaw('((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate) BETWEEN 51 AND 80');
                    break;
                case 'very_high':
                    $query->whereRaw('((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate) > 80');
                    break;
            }
        }

        // Filtro por rango de fechas de creación
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->input('endDate'));
        }

        // Ordenamiento
        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $sortBy = $request->input('sortBy');
            $orderBy = $request->input('orderBy');

            // Ordenamientos especiales que requieren cálculos
            if ($sortBy === 'current_value') {
                $query->orderByRaw("cost * GREATEST(0, 1 - ((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate / 100)) {$orderBy}");
            } elseif ($sortBy === 'depreciation_rate') {
                $query->orderByRaw("((YEAR(NOW()) - acquisition_year) * annual_depreciation_rate) {$orderBy}");
            } else {
                // Ordenamientos normales
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Ordenamiento por defecto
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }
    public function calculateTotalDepreciation(): float
    {
        $furniture = Furniture::all();
        $totalDepreciation = 0;

        foreach ($furniture as $item) {
            $originalCost = $item->cost;
            $currentValue = $item->getCurrentValue();
            $depreciationAmount = $originalCost - $currentValue;
            $totalDepreciation += $depreciationAmount;
        }

        return (float) $totalDepreciation;
    }
}
