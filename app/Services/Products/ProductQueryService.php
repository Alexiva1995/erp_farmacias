<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductQueryService
{
    /**
     * Prepara la consulta base para los productos.
     */
    private function getBaseQuery(): Builder
    {
        return Product::query()->select('products.*')->with([
            'category',
            'laboratory',
            'origin',
            'group',
            'profitability',
            'lots' => function ($query) {
                $query->where('quantity', '>', 0);
            },
        ]);
    }

    /**
     * Aplica los filtros a la consulta de productos.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['null_barcodes'])) {
            $query->whereNull('barcode');
        }

        if (!empty($filters['null_laboratory'])) {
            $query->whereNull('laboratory_id');
        }

        if (!empty($filters['null_origin'])) {
            $query->whereNull('origin_id');
        }

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {

                if ($isStrictSearch) {
                    $subQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('active_ingredient', 'like', "%{$searchTerm}%")
                        ->orWhere('barcode', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $subQuery->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('name', 'like', "%{$word}%")
                                ->orWhere('active_ingredient', 'like', "%{$word}%")
                                ->orWhere('id', 'like', "%{$word}%")
                                ->orWhereHas('laboratory', function ($labQuery) use ($word) {
                                    $labQuery->where('name', 'like', "%{$word}%");
                                });
                        });
                    }
                }
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['is_psychotropic'])) {
            $query->where('psychotropic', $filters['is_psychotropic']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }
        // filtro de profitability is_locked
        if (!empty($filters['lockedValue'])) {

            switch ($filters['lockedValue']) {
                case 2:
                    $query->whereHas('profitability', function ($query) {
                        $query->where("is_locked", 1);
                    });
                    break;

                case 1:
                    $query->whereDoesntHave('profitability')
                        ->orWhereHas('profitability', function ($q) {
                            $q->where('is_locked', '!=', 1);
                        });
                    break;
            }
        }

        $hasStock = $filters['hasStock'] ?? null;

        if ($hasStock === false) {
            // Sin stock: verificar que la suma de lotes válidos sea 0 o que no existan lotes válidos
            $query->whereRaw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE()), 0) <= 0');
        } elseif ($hasStock === true) {
            // Con stock: verificar que la suma de lotes válidos sea mayor que 0
            $query->whereRaw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE()), 0) > 0');
        }

        // Filtros de fecha para lotes (independientes del filtro de stock)
        if (!empty($filters['startDate']) || !empty($filters['endDate'])) {
            $query->whereHas('lots', function ($lotQuery) use ($filters) {
                if (!empty($filters['startDate'])) {
                    $lotQuery->where('expiration_date', '>=', $filters['startDate']);
                }
                if (!empty($filters['endDate'])) {
                    $lotQuery->where('expiration_date', '<=', $filters['endDate']);
                }
            });
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de productos.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        /*if (empty($sortBy)) {
            return $query->orderBy('products.name', 'asc');
        }*/

        $sortBy = $sortBy ?? 'name';

        switch ($sortBy) {
            case 'laboratory.name':
                return $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'valid_stock':
                $subQuery = DB::raw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE()), 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'next_expiration':
                $subQuery = DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE())');
                return $query->orderBy($subQuery, $orderBy);

            case 'most_sold':
                $subQuery = DB::raw('COALESCE((SELECT SUM(order_details.quantity) FROM order_details WHERE order_details.product_id = products.id), 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'id':
                return $query->orderBy('products.id', $orderBy);
            case 'product.name':
                return $query->orderBy('products.name', $orderBy);
                break;
            case 'created_at':
                return $query->orderBy('created_at', $orderBy);
                break;
            case 'sale_price':
                return $query->orderBy("products.{$sortBy}", $orderBy);
            default:
                return $query->orderBy('products.name', $orderBy);
        }

        return $query;
    }

    public function searchBarcodeProduct(Request $request)
    {
        $product = Product::where('barcode', $request->barcode)
            ->with(['laboratory', 'origin', 'category'])
            ->first();
        return $product;
    }
    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'groupId' => $request->groupId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $this->applyFilters($query, $filters);
        $this->subColummn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getPendingProductsQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'groupId' => $request->groupId,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
            'null_barcodes' => true
        ];

        $this->applyFilters($query, $filters);
        $this->subColummn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getProductsWithoutLaboratoryQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'originId' => $request->originId,
            'groupId' => $request->groupId,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
            'null_laboratory' => true
        ];

        $this->applyFilters($query, $filters);
        $this->subColummn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getProductsWithoutOriginQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'groupId' => $request->groupId,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
            'null_origin' => true
        ];

        $this->applyFilters($query, $filters);
        $this->subColummn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function subColummn(Builder $query): Builder
    {
        return $query->addSelect([
            'stock_calculado' => DB::raw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE()), 0) as stock_calculado'),
            'ultima_fecha_vencimiento' => DB::table('product_lots')
                ->selectRaw('MAX(expiration_date)')
                ->whereColumn('product_lots.product_id', 'products.id'),
            // ->where('product_lots.expiration_date', '>=', DB::raw('CURDATE()')),
            // 'fecha_vencimiento_siguiente_lote' => DB::table('product_lots')
            //     ->whereColumn('product_lots.product_id', 'products.id')
            //     ->selectRaw('MIN(expiration_date)')
            //     ->where('product_lots.quantity', '>', 0)
            //     ->where('product_lots.expiration_date', '>=', DB::raw('CURDATE()'))
            //     ->orderBy('product_lots.expiration_date', 'ASC'),
        ]);
    }
    public function calculateInventoryValue(): float
    {
        $totalValue = Product::selectRaw('SUM(stock * unit_cost) as total_value')
            ->where('stock', '>', 0)
            ->where('unit_cost', '>', 0)
            ->value('total_value');

        return (float) ($totalValue ?? 0);
    }
}
