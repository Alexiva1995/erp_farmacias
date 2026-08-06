<?php

declare(strict_types=1);

namespace App\Services\CleaningActivities;

use App\Models\CleaningActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CleaningActivityQueryService
{
    /**
     * Prepara la consulta base para las actividades con campos explícitos.
     */
    private function getBaseQuery(): Builder
    {
        return CleaningActivity::query()->select([
            'id',
            'activity',
            'description',
            'frequency',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Aplica los filtros a la consulta de actividades.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $query->search($filters['q'], $filters['isStrictSearch'] ?? false);
        }

        if (!empty($filters['frequency'])) {
            $query->byFrequency($filters['frequency']);
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de actividades.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('activity', 'asc');
        }

        $allowedSortFields = ['id', 'activity', 'frequency', 'created_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            return $query->orderBy($sortBy, $orderBy);
        }

        return $query->orderBy('activity', 'asc');
    }

    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'frequency' => $request->frequency,
            'isStrictSearch' => $request->boolean('isStrictSearch'),
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
