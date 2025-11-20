<?php

namespace App\Services\Groups;

use App\Models\GroupsProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class GroupQueryService
{
    /**
     * Aplica los filtros de búsqueda a la consulta de grupos.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        $filters = [
            'q' => $request->q,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $subQuery->where('groups_products.name', 'like', $searchTerm)
                        ->orWhereHas('products', function ($productQuery) use ($searchTerm) {
                            $productQuery->where('name', 'like', $searchTerm)
                                ->orWhere('active_ingredient', 'like', $searchTerm)
                                ->orWhere('barcode', 'like', $searchTerm)
                                ->orWhere('id', 'like', $searchTerm);
                        });
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $searchWord = "%{$word}%";
                        $subQuery->where(function ($wordQuery) use ($searchWord) {
                            $wordQuery->where('groups_products.name', 'like', $searchWord)
                                ->orWhereHas('products', function ($productQuery) use ($searchWord) {
                                    $productQuery->where('name', 'like', $searchWord)
                                        ->orWhere('active_ingredient', 'like', $searchWord);
                                });
                        });
                    }
                }
            });
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de grupos.
     */
    private function applySorting(Builder $query, Request $request): Builder
    {
        $sortBy = $request->input('sortBy', 'name');
        $orderBy = $request->input('orderBy', 'asc');

        if (in_array($sortBy, ['id', 'name'])) {
            $query->orderBy($sortBy, $orderBy);
        }
        return $query;
    }

    /**
     * Obtiene una lista paginada de grupos.
     */
    public function getPaginatedGroups(Request $request): LengthAwarePaginator
    {
        $query = GroupsProduct::query()->with(['products']);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = $request->input('itemsPerPage', 10);
        return $query->paginate($perPage);
    }

    /**
     * Busca un único grupo por su ID o nombre.
     */
    public function findGroup(string $searchTerm): ?GroupsProduct
    {
        $query = GroupsProduct::query();

        if (is_numeric($searchTerm)) {
            return $query->find($searchTerm);
        }

        return $query->where('name', 'like', $searchTerm)->first();
    }

    /**
     * consultar todos los grupos ordenados por nombre
     */
    public function consultAll(): Collection
    {
        return GroupsProduct::query()->orderBy("name", "ASC")->get();
    }
}
