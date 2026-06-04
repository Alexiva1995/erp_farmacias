<?php

namespace App\Services\Quotation;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QuotationQueryService
{
    private function getBaseQueryProduct(array $filters = []): QueryBuilder
    {
        $resourceService = app(\App\Services\Resources\ResourceService::class);
        $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
        $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;

        $generalSettings = DB::table('general_settings')->first();
        $isRestaurant = $generalSettings && $generalSettings->business_type === 'restaurant';

        // 1. Consulta de PRODUCTOS
        $productsQuery = DB::table('products')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select([
                'products.id',
                'products.name',
                'products.sale_price',
                DB::raw("ROUND(products.sale_price * {$tasaBs}, 2) as price_bs"),
                DB::raw("ROUND(products.sale_price * {$tasaCop}, 2) as price_cop"),
                'products.active_ingredient',
                'products.laboratory_id',
                'products.group_id',
                'products.origin_id',
                'products.sales_average',
                'products.iva',
                'products.is_colombian_origin',
                'products.psychotropic',
                'laboratories.name as laboratory_name',
                DB::raw('NULL as pack_config'),
                DB::raw("'product' as item_type"),
                DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.quantity > 0) as next_expiration'),
                DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0), 0) as valid_stock_sum'),
                DB::raw("(SELECT GREATEST(COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0), COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0), COALESCE((SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0 AND (TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) + 1) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1), 0)) ) as discount_percentage"),
                DB::raw("(SELECT CASE WHEN COALESCE((SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0 AND (TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) + 1) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1), 0) >= GREATEST(COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0), COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0)) AND (SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0 AND (TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) + 1) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1) > 0 THEN 'expiration' WHEN COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0) >= COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0) THEN 'individual' WHEN (SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1) > 0 THEN 'category' ELSE NULL END) as discount_type"),
            ])
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')->orWhere('products.is_deleted', 0);
            })
            ->where('products.no_pvp', $isRestaurant ? 1 : 0);

        // 2. Consulta de PACKS
        $packsQuery = DB::table('product_packs')
            ->select([
                'product_packs.id',
                'product_packs.name',
                'product_packs.total_price as sale_price',
                DB::raw("ROUND(product_packs.total_price * {$tasaBs}, 2) as price_bs"),
                DB::raw("ROUND(product_packs.total_price * {$tasaCop}, 2) as price_cop"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT(p.name, ' [', COALESCE(p.active_ingredient, 'S/I'), ' - ', COALESCE(l.name, 'S/L'), ']') SEPARATOR ' ') FROM products p LEFT JOIN laboratories l ON p.laboratory_id = l.id WHERE JSON_CONTAINS(JSON_KEYS(product_packs.pack_config), CAST(JSON_QUOTE(CAST(p.id AS CHAR)) AS JSON))) as active_ingredient"),
                DB::raw('NULL as laboratory_id'),
                DB::raw('NULL as group_id'),
                DB::raw('NULL as origin_id'),
                DB::raw('NULL as sales_average'),
                DB::raw('0 as iva'),
                DB::raw('0 as is_colombian_origin'),
                DB::raw('0 as psychotropic'),
                DB::raw("'' as laboratory_name"),
                DB::raw("product_packs.pack_config as pack_config"),
                DB::raw("'pack' as item_type"),
                'product_packs.max_sale_date as next_expiration',
                'product_packs.max_quantity as valid_stock_sum',
                DB::raw('NULL as discount_percentage'),
                DB::raw('NULL as discount_type'),
            ])->where('product_packs.is_active', true)
            ->whereNull('product_packs.deleted_at')
            ->where(function ($q) {
                $q->whereNull('product_packs.max_sale_date')
                    ->orWhere('product_packs.max_sale_date', '>=', DB::raw("'" . now()->toDateString() . "'"));
            });

        // 3. Consulta de PLATILLOS (Solo para restaurantes)
        $dishesQuery = null;
        if ($isRestaurant) {
            $dishesQuery = DB::table('dishes')
                ->select([
                    'dishes.id',
                    'dishes.name',
                    'dishes.designated_price as sale_price',
                    DB::raw("ROUND(dishes.designated_price * {$tasaBs}, 2) as price_bs"),
                    DB::raw("ROUND(dishes.designated_price * {$tasaCop}, 2) as price_cop"),
                    DB::raw("'PLATILLO' as active_ingredient"),
                    DB::raw('NULL as laboratory_id'),
                    DB::raw('NULL as group_id'),
                    DB::raw('NULL as origin_id'),
                    DB::raw('NULL as sales_average'),
                    DB::raw('0 as iva'),
                    DB::raw('0 as is_colombian_origin'),
                    DB::raw('0 as psychotropic'),
                    DB::raw("'MENÚ' as laboratory_name"),
                    DB::raw('NULL as pack_config'),
                    DB::raw("'dish' as item_type"),
                    DB::raw('NULL as next_expiration'),
                    DB::raw('9999 as valid_stock_sum'),
                    DB::raw('NULL as discount_percentage'),
                    DB::raw('NULL as discount_type'),
                ])->where('dishes.status', '1');
        }

        // APLICAR BUSCADOR 'Q' A AMBOS LADOS
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $searchTermLower = strtolower(trim($searchTerm));
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $isColombianSearch = in_array($searchTermLower, ['col', '(col)', 'colombiano', 'colombianos']);
            $isIvaSearch = in_array($searchTermLower, ['g', '(g)', 'iva', 'gravado']);

            if ($isColombianSearch || $isIvaSearch) {
                $packsQuery->whereRaw('1 = 0');
                if ($dishesQuery) {
                    $dishesQuery->whereRaw('1 = 0');
                }
            }

            // Filtro para PRODUCTOS
            $productsQuery->where(function ($subQuery) use ($searchTerm, $isStrictSearch, $isColombianSearch, $isIvaSearch) {
                if ($isColombianSearch) {
                    $subQuery->where('products.is_colombian_origin', 1);
                }
                elseif ($isIvaSearch) {
                    $subQuery->where('products.iva', 1);
                }
                else {
                    if ($isStrictSearch) {
                        $subQuery->where('products.name', 'like', "%{$searchTerm}%")
                            ->orWhere('products.active_ingredient', 'like', "%{$searchTerm}%");
                    } else {
                        $words = explode(' ', $searchTerm);
                        foreach ($words as $word) {
                            $subQuery->where(function ($wordQuery) use ($word) {
                                $wordQuery->where('products.name', 'like', "%{$word}%")
                                    ->orWhere('products.active_ingredient', 'like', "%{$word}%")
                                    ->orWhere('laboratories.name', 'like', "%{$word}%");
                            });
                        }
                    }
                }
            });

            // Filtro para PACKS (solo si no es búsqueda especial)
            if (!$isColombianSearch && !$isIvaSearch) {
                $packsQuery->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {
                    if ($isStrictSearch) {
                        $subQuery->where('product_packs.name', 'like', "%{$searchTerm}%");
                    } else {
                        $words = explode(' ', $searchTerm);
                        foreach ($words as $word) {
                            $subQuery->where('product_packs.name', 'like', "%{$word}%");
                        }
                    }
                });
            }

            // Filtro para PLATILLOS (solo si no es búsqueda especial)
            if ($dishesQuery && !$isColombianSearch && !$isIvaSearch) {
                $dishesQuery->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {
                    if ($isStrictSearch) {
                        $subQuery->where('dishes.name', 'like', "%{$searchTerm}%");
                    } else {
                        $words = explode(' ', $searchTerm);
                        foreach ($words as $word) {
                            $subQuery->where('dishes.name', 'like', "%{$word}%");
                        }
                    }
                });
            }
        }

        // APLICAR OTROS FILTROS
        if (!empty($filters['categoryId'])) {
            $productsQuery->where('products.category_id', $filters['categoryId']);
            $packsQuery->whereRaw('1 = 0');
            if ($dishesQuery) {
                $dishesQuery->where('dishes.category_id', $filters['categoryId']);
            }
        }

        if (!empty($filters['laboratoryId'])) {
            $productsQuery->where('products.laboratory_id', $filters['laboratoryId']);
            $packsQuery->whereRaw('1 = 0');
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 0');
            }
        }

        if (!empty($filters['originId'])) {
            $productsQuery->where('origin_id', $filters['originId']);
            $packsQuery->whereRaw('1 = 0');
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 0');
            }
        }

        if (!empty($filters['groupId'])) {
            $productsQuery->where('products.group_id', $filters['groupId']);
            $packsQuery->whereRaw('1 = 0');
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 0');
            }
        }

        $hasStock = $filters['hasStock'] ?? null;
        if ($hasStock === true) {
            $productsQuery->whereRaw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0), 0) > 0');
            $packsQuery->where('product_packs.max_quantity', '>', 0);
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 1');
            }
        } elseif ($hasStock === false) {
            $productsQuery->whereRaw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0), 0) = 0');
            $packsQuery->where('product_packs.max_quantity', '=', 0);
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 0');
            }
        }

        if ($dishesQuery) {
            return $productsQuery->unionAll($packsQuery)->unionAll($dishesQuery);
        }
        return $productsQuery->unionAll($packsQuery);
    }

    private function applySortingProduct($query, ?string $sortBy, string $orderBy)
    {
        $query->orderByRaw("CASE 
            WHEN item_type = 'pack' THEN 0 
            WHEN discount_percentage > 0 THEN 1 
            ELSE 2 
        END ASC");

        $query->orderBy('discount_percentage', 'desc');

        if (empty($sortBy)) {
            return $query->orderBy('name', 'asc');
        }

        switch ($sortBy) {
            case 'laboratory.name':
            case 'laboratory_name':
                return $query->orderBy('laboratory_name', $orderBy);
            case 'valid_stock':
            case 'lots_sum_quantity':
            case 'valid_stock_sum':
                return $query->orderBy('valid_stock_sum', $orderBy);
            case 'name':
                return $query->orderBy('name', $orderBy);
            case 'sale_price':
            case 'price':
                return $query->orderBy('sale_price', $orderBy);
            case 'sales_average':
                return $query->orderBy('sales_average', $orderBy);
            case 'next_expiration':
                return $query->orderBy('next_expiration', $orderBy);
            default:
                return $query->orderBy('name', 'asc');
        }
    }

    public function getCountQueryProduct(Request $request): QueryBuilder
    {
        $filters = [
            'q' => $request->q,
            'categoryId' => $request->categoryId,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'groupId' => $request->get('groupId'),
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $unionQuery = $this->getBaseQueryProduct($filters);
        $sql = $unionQuery->toSql();
        $bindings = $unionQuery->getBindings();

        return DB::table(DB::raw("($sql) as tpv_count"))
            ->setBindings($bindings);
    }

    public function getFilteredQuery(Request $request): QueryBuilder
    {
        $filters = [
            'q' => $request->q,
            'categoryId' => $request->categoryId,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'groupId' => $request->get('groupId'),
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $unionQuery = $this->getBaseQueryProduct($filters);

        $query = DB::table(DB::raw("({$unionQuery->toSql()}) as tpv_items"))
            ->mergeBindings($unionQuery)
            ->select('*');

        $this->applySortingProduct($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
