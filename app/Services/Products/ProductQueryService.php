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
        return Product::query()
            ->select('products.*')
            ->selectRaw("COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) AS stock_calculado")
            ->with([
            'category',
            'laboratory',
            'origin',
            'group',
            'profitability',
            'productSuppliers',
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

        if (!empty($filters['null_group'])) {
            $query->whereNull('group_id');
        }

        // Si hay productId, priorizar búsqueda directa por ID (omitir filtro q para evitar conflictos)
        if (!empty($filters['productId'])) {
            $query->where('products.id', (int) $filters['productId']);
        } elseif (!empty($filters['q'])) {
            $searchTerm = trim($filters['q']);
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {

                if ($isStrictSearch) {
                    // Búsqueda estricta: usar REGEXP con límites de palabra para coincidencias exactas
                    // Esto evita que "loratadina" coincida con "desloratadina"
                    $escapedTerm = preg_quote($searchTerm, '/');
                    $pattern = "(^|[^a-zA-Z0-9]){$escapedTerm}([^a-zA-Z0-9]|$)";
                    $subQuery->whereRaw("name REGEXP ?", [$pattern])
                        ->orWhereRaw("active_ingredient REGEXP ?", [$pattern])
                        ->orWhere('barcode', '=', $searchTerm)
                        ->orWhere('id', '=', $searchTerm);
                } else {
                    // Búsqueda normal: permite coincidencias parciales por nombre e ID exacto
                    // Si el término completo es numérico: priorizar búsqueda por ID exacto
                    $isNumericSearch = ctype_digit($searchTerm);
                    if ($isNumericSearch) {
                        $productId = (int) $searchTerm;
                        $subQuery->where('id', '=', $productId)
                            ->orWhere('name', 'like', "%{$searchTerm}%")
                            ->orWhere('active_ingredient', 'like', "%{$searchTerm}%")
                            ->orWhere('barcode', 'like', "%{$searchTerm}%")
                            ->orWhereHas('laboratory', function ($labQuery) use ($searchTerm) {
                                $labQuery->where('name', 'like', "%{$searchTerm}%");
                            });
                    } else {
                        $words = explode(' ', $searchTerm);
                        $words = array_filter(array_map('trim', $words));
                        foreach ($words as $word) {
                            if (empty($word)) continue;
                            $wordPattern = "%{$word}%";
                            $subQuery->where(function ($wordQuery) use ($wordPattern, $word) {
                                // Para palabras numéricas: ID exacto; para el resto: LIKE
                                $wordQuery->where('name', 'like', $wordPattern)
                                    ->orWhere('active_ingredient', 'like', $wordPattern)
                                    ->orWhere('barcode', 'like', $wordPattern)
                                    ->orWhereHas('laboratory', function ($labQuery) use ($wordPattern) {
                                        $labQuery->where('name', 'like', $wordPattern);
                                    });
                                if (ctype_digit($word)) {
                                    $wordQuery->orWhere('id', '=', (int) $word);
                                } else {
                                    $wordQuery->orWhere('id', 'like', $wordPattern);
                                }
                            });
                        }
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

        if (!empty($filters['supplierId'])) {
            $query->whereHas('productSuppliers', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplierId']);
            });
        }

        // Filtrar productos sin grupo o del grupo actual (útil para añadir productos a un grupo)
        if (!empty($filters['withoutGroupOrCurrentGroup'])) {
            $currentGroupId = $filters['withoutGroupOrCurrentGroup'];
            $query->where(function ($q) use ($currentGroupId) {
                $q->whereNull('group_id')
                    ->orWhere('group_id', $currentGroupId);
            });
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
            // Sin stock: verificar que la suma de lotes sea 0
            $query->whereRaw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) <= 0');
        } elseif ($hasStock === true) {
            // Con stock: verificar que la suma de lotes sea mayor que 0
            $query->whereRaw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) > 0');
        }

        // Filtros de fecha para lotes (independientes del filtro de stock)
        // Se considera un lote "activo" en el rango si:
        // 1. Fue creado antes o durante el fin del periodo (created_at <= endDate)
        // 2. No había vencido antes del inicio del periodo (expiration_date >= startDate)
        if (!empty($filters['startDate']) || !empty($filters['endDate'])) {
            $query->whereHas('lots', function ($lotQuery) use ($filters) {
                $lotQuery->where('quantity', '>', 0); // Solo lotes con unidades activas

                if (!empty($filters['startDate'])) {
                    $lotQuery->where('expiration_date', '>=', $filters['startDate']);
                }
                if (!empty($filters['endDate'])) {
                    // Usar created_at para asegurar que el lote ya existía en esa fecha
                    $lotQuery->where('created_at', '<=', $filters['endDate'] . ' 23:59:59');
                }
            });
        }

        // Filtrar solo productos redundantes (is_scarce = true)
        if (!empty($filters['isScarce'])) {
            $query->where('is_scarce', true);
        }

        if (!empty($filters['onlyDeleted'])) {
            $query->withoutGlobalScope('not_deleted')->where('is_deleted', true);
        }

        if (!empty($filters['product_type'])) {
            switch ($filters['product_type']) {
                case 'redundantes':
                    $query->where('is_scarce', true);
                    break;
                case 'col':
                    $query->where('is_colombian_origin', true);
                    break;
                case 'iva':
                    $query->where('iva', 1);
                    break;
                case 'exento':
                    $query->where(function ($q) {
                        $q->where('iva', 0)->orWhereNull('iva');
                    });
                    break;
                case 'novaventa':
                    $query->where('is_novaventa', true);
                    break;
                case 'eliminados':
                    $query->withoutGlobalScope('not_deleted')->where('is_deleted', true);
                    break;
            }
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
        // Validar que orderBy sea 'asc' o 'desc' para seguridad
        $orderBy = in_array(strtolower($orderBy), ['asc', 'desc']) ? strtolower($orderBy) : 'asc';

        switch ($sortBy) {
            case 'laboratory':
            case 'laboratory.name':
                return $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'valid_stock':
                return $query->orderByRaw("COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) $orderBy");

            case 'stock_calculado':
                // Ordenar por stock calculado como número
                return $query->orderByRaw("CAST(COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) AS UNSIGNED) $orderBy");

            case 'next_expiration':
                $subQuery = '(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE())';
                return $query->orderByRaw("($subQuery) IS NULL, ($subQuery) $orderBy");

            case 'most_sold':
                return $query->orderByRaw("COALESCE((SELECT SUM(order_details.quantity) FROM order_details WHERE order_details.product_id = products.id), 0) $orderBy");

            case 'id':
                return $query->orderBy('products.id', $orderBy);
            case 'name':
            case 'product.name':
                return $query->orderBy('products.name', $orderBy);
            case 'created_at':
                return $query->orderBy('created_at', $orderBy);
                break;
            case 'unit_cost':
                // Ordenar por costo unitario como número decimal
                return $query->orderByRaw("CAST(products.unit_cost AS DECIMAL(10,2)) " . strtoupper($orderBy));
            case 'sale_price':
                // Ordenar por precio de venta como número decimal
                return $query->orderByRaw("CAST(products.sale_price AS DECIMAL(10,2)) " . strtoupper($orderBy));
            default:
                return $query->orderBy('products.name', $orderBy);
        }

        return $query;
    }

    public function searchBarcodeProduct(Request $request)
    {
        $product = Product::where('barcode', $request->barcode)
            ->where('no_pvp', '!=', 1)
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
            'productId' => $request->productId ?? $request->product_id ?? $request->id,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'groupId' => $request->groupId,
            'supplierId' => $request->supplierId ?? $request->supplier_id,
            'withoutGroupOrCurrentGroup' => $request->withoutGroupOrCurrentGroup,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
            'isScarce'       => filter_var($request->get('isScarce'), FILTER_VALIDATE_BOOLEAN),
            'onlyDeleted'    => filter_var($request->get('onlyDeleted'), FILTER_VALIDATE_BOOLEAN),
            'product_type'   => $request->input('product_type') ?? $request->input('productType'),
        ];

        $this->applyFilters($query, $filters);
        $this->subColumn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getIncompleteProductsQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        $query->where(function ($q) use ($isRestaurant) {
            $q->whereNull('barcode')
                ->orWhereNull('laboratory_id');
            
            if (!$isRestaurant) {
                $q->orWhereNull('origin_id');
            }
        });

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
            'is_pending' => true,
            'product_type'   => $request->input('product_type') ?? $request->input('productType'),
        ];

        $this->applyFilters($query, $filters);
        $this->subColumn($query);
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
            'null_laboratory' => true,
            'product_type'   => $request->input('product_type') ?? $request->input('productType'),
        ];

        $this->applyFilters($query, $filters);
        $this->subColumn($query);
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
            'null_origin' => true,
            'product_type'   => $request->input('product_type') ?? $request->input('productType'),
        ];

        $this->applyFilters($query, $filters);
        $this->subColumn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getProductsWithoutGroupQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'lockedValue' => $request->lockedValue,
            'is_psychotropic' => $request->is_psychotropic,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
            'null_group' => true,
            'product_type'   => $request->input('product_type') ?? $request->input('productType'),
        ];

        $this->applyFilters($query, $filters);
        $this->subColumn($query);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function subColumn(Builder $query): Builder
    {
        return $query->selectRaw('COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) as stock_calculado')
            ->addSelect([
                'ultima_fecha_vencimiento' => DB::table('product_lots')
                    ->selectRaw('MAX(expiration_date)')
                    ->whereColumn('product_lots.product_id', 'products.id'),
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
