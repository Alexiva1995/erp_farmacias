<?php

namespace App\Services\Quotation;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationQueryService
{

    private function getBaseQuery(): Builder
    {
        return Product::query()->select(
            'products.*'
        )
            ->with([
                'laboratory',
                'origin',
            ])
            // --- ¡ESTE ES EL CAMBIO CLAVE! ---
            // Usamos addSelect con DB::raw para crear la columna 'valid_stock_sum'
            // y aplicar COALESCE directamente en la subconsulta de la suma.
            ->addSelect(DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) as valid_stock_sum'));
        // --- FIN CAMBIO CLAVE ---
    }


    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $subQuery->where(function ($subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'like', $searchTerm)
                            ->orWhere('active_ingredient', 'like', $searchTerm)
                            ->orWhere('barcode', 'like', $searchTerm);
                    });
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $searchWord = "%{$word}%";
                        $subQuery->where(function ($wordQuery) use ($searchWord) {
                            $wordQuery->where('name', 'like', $searchWord)
                                ->orWhere('active_ingredient', 'like', $searchWord)
                                ->orWhere('barcode', 'like', $searchWord);
                            ;
                        });
                    }
                }
            });
        }


        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }

        $hasStock = $filters['hasStock'] ?? null;

        // La lógica de HAVING ahora funciona porque 'valid_stock_sum'
        // es una columna real en el SELECT de la consulta.
        if ($hasStock === true) {
            $query->groupBy('products.id')
                ->havingRaw('valid_stock_sum > 0');
        } elseif ($hasStock === false) {
            $query->groupBy('products.id')
                ->havingRaw('valid_stock_sum <= 0');
        }

        return $query;
    }

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
            case 'lots_sum_quantity':
            case 'valid_stock_sum': // Aseguramos que se pueda ordenar por el alias correcto
                return $query->orderBy('valid_stock_sum', $orderBy);

            case 'next_expiration':
                $subQuery = DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE() AND product_lots.quantity > 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'sales_average':
                return $query->orderBy('products.sales_average', $orderBy);

            case 'id':
            case 'name':
            case 'cost_price':
            case 'sale_price':
                return $query->orderBy("products.{$sortBy}", $orderBy);
        }

        return $query;
    }

    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'isStrictSearch' => filter_var($request->isStrictSearch, FILTER_VALIDATE_BOOLEAN),
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
