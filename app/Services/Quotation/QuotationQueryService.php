<?php

declare(strict_types=1);

namespace App\Services\Quotation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QuotationQueryService
{
    /**
     * Caché de configuración general por ciclo de vida del servicio.
     * Evita múltiples hits a general_settings por request.
     */
    private ?object $cachedSettings = null;
    private ?float  $cachedTasaBs  = null;
    private ?float  $cachedTasaCop = null;

    // -------------------------------------------------------------------------
    // Helpers de configuración cacheada
    // -------------------------------------------------------------------------

    private function getSettings(): object
    {
        if ($this->cachedSettings === null) {
            $this->cachedSettings = DB::table('general_settings')->first() ?? new \stdClass();
        }

        return $this->cachedSettings;
    }

    private function getTasaBs(): float
    {
        if ($this->cachedTasaBs === null) {
            $resourceService  = app(\App\Services\Resources\ResourceService::class);
            $this->cachedTasaBs  = (float) ($resourceService->getExchangeRate('BS')  ?: 1);
            $this->cachedTasaCop = (float) ($resourceService->getExchangeRate('COP') ?: 1);
        }

        return $this->cachedTasaBs;
    }

    private function getTasaCop(): float
    {
        // Garantiza que ambas tasas se resuelvan en una sola llamada
        $this->getTasaBs();

        return $this->cachedTasaCop;
    }

    // -------------------------------------------------------------------------
    // Query base — productos + packs + (platillos si aplica)
    // -------------------------------------------------------------------------

    /**
     * Construye el UNION base sin paginación ni ordenamiento.
     * Se llama UNA SOLA VEZ por request; el controlador envuelve
     * el resultado en un subquery para contar y otro para paginar.
     */
    private function getBaseQueryProduct(array $filters = []): QueryBuilder
    {
        $tasaBs      = $this->getTasaBs();
        $tasaCop     = $this->getTasaCop();
        $isRestaurant = isset($this->getSettings()->business_type)
            && $this->getSettings()->business_type === 'restaurant';

        // ── 1. Subconsultas de descuento extraídas como expresiones reutilizables ──
        // Se definen como cadenas para evitar repetición en discount_percentage/type.
        $ioDiscount = "(SELECT io.discount_percent
                          FROM individual_offers io
                         WHERE io.product_id = products.id
                           AND io.start_date <= CURDATE()
                           AND io.end_date   >= CURDATE()
                         ORDER BY io.discount_percent DESC LIMIT 1)";

        $coDiscount = "(SELECT co.discount_percentage
                          FROM category_offers co
                         WHERE co.category_id = products.category_id
                           AND co.is_active   = 1
                           AND co.start_date <= CURDATE()
                           AND co.end_date   >= CURDATE()
                         ORDER BY co.discount_percentage DESC LIMIT 1)";

        // La oferta por vencimiento se calcula una vez usando un JOIN lateral-style
        // para evitar repetir el EXISTS doble en discount_percentage y discount_type.
        $eoDiscount = "(SELECT eo.discount_percentage
                          FROM expiration_offers eo
                         WHERE eo.is_active = 1
                           AND EXISTS (
                               SELECT 1 FROM product_lots pl
                                WHERE pl.product_id = products.id
                                  AND pl.quantity   > 0
                                  AND (TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) + 1) <= eo.months_to_expiration
                           )
                         ORDER BY eo.discount_percentage DESC LIMIT 1)";

        $discountPercentageRaw = "GREATEST(
            COALESCE({$ioDiscount}, 0),
            COALESCE({$coDiscount}, 0),
            COALESCE({$eoDiscount}, 0)
        ) as discount_percentage";

        $discountTypeRaw = "CASE
            WHEN COALESCE({$eoDiscount}, 0) >= GREATEST(
                    COALESCE({$ioDiscount}, 0),
                    COALESCE({$coDiscount}, 0)
                 )
                 AND COALESCE({$eoDiscount}, 0) > 0
                 THEN 'expiration'
            WHEN COALESCE({$ioDiscount}, 0) >= COALESCE({$coDiscount}, 0)
                 AND COALESCE({$ioDiscount}, 0) > 0
                 THEN 'individual'
            WHEN COALESCE({$coDiscount}, 0) > 0
                 THEN 'category'
            ELSE NULL
        END as discount_type";

        // ── 2. Consulta de PRODUCTOS ──
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
                // next_expiration: solo el MIN, sin repetir EXISTS complejos
                DB::raw('(SELECT MIN(pl.expiration_date)
                            FROM product_lots pl
                           WHERE pl.product_id = products.id
                             AND pl.quantity   > 0) as next_expiration'),
                // valid_stock_sum: lotes no expirados con cantidad > 0
                DB::raw('COALESCE((SELECT SUM(pl.quantity)
                                     FROM product_lots pl
                                    WHERE pl.product_id = products.id
                                      AND pl.quantity   > 0
                                      AND pl.expiration_date >= CURDATE()), 0) as valid_stock_sum'),
                DB::raw($discountPercentageRaw),
                DB::raw($discountTypeRaw),
            ])
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')->orWhere('products.is_deleted', 0);
            })
            ->where('products.no_pvp', $isRestaurant ? 1 : 0);

        // ── 3. Consulta de PACKS ──
        $packsQuery = DB::table('product_packs')
            ->select([
                'product_packs.id',
                'product_packs.name',
                'product_packs.total_price as sale_price',
                DB::raw("ROUND(product_packs.total_price * {$tasaBs}, 2) as price_bs"),
                DB::raw("ROUND(product_packs.total_price * {$tasaCop}, 2) as price_cop"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT(p.name, ' [', COALESCE(p.active_ingredient, 'S/I'), ' - ', COALESCE(l.name, 'S/L'), ']') SEPARATOR ' ')
                            FROM products p
                            LEFT JOIN laboratories l ON p.laboratory_id = l.id
                           WHERE JSON_CONTAINS(JSON_KEYS(product_packs.pack_config), CAST(JSON_QUOTE(CAST(p.id AS CHAR)) AS JSON))
                          ) as active_ingredient"),
                DB::raw('NULL as laboratory_id'),
                DB::raw('NULL as group_id'),
                DB::raw('NULL as origin_id'),
                DB::raw('NULL as sales_average'),
                DB::raw('0 as iva'),
                DB::raw('0 as is_colombian_origin'),
                DB::raw('0 as psychotropic'),
                DB::raw("'' as laboratory_name"),
                DB::raw('product_packs.pack_config as pack_config'),
                DB::raw("'pack' as item_type"),
                'product_packs.max_sale_date as next_expiration',
                'product_packs.max_quantity as valid_stock_sum',
                DB::raw('NULL as discount_percentage'),
                DB::raw('NULL as discount_type'),
            ])
            ->where('product_packs.is_active', true)
            ->whereNull('product_packs.deleted_at')
            ->where(function ($q) {
                $q->whereNull('product_packs.max_sale_date')
                    ->orWhere('product_packs.max_sale_date', '>=', DB::raw("'" . now()->toDateString() . "'"));
            });

        // ── 4. Consulta de PLATILLOS (solo restaurante) ──
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

        // ── 5. Aplicar filtro de búsqueda ──
        if (!empty($filters['q'])) {
            $searchTerm      = $filters['q'];
            $searchTermLower = strtolower(trim($searchTerm));
            $isStrictSearch  = $filters['isStrictSearch'] ?? false;

            $isColombianSearch = in_array($searchTermLower, ['col', '(col)', 'colombiano', 'colombianos']);
            $isIvaSearch       = in_array($searchTermLower, ['g', '(g)', 'iva', 'gravado']);

            if ($isColombianSearch || $isIvaSearch) {
                $packsQuery->whereRaw('1 = 0');
                if ($dishesQuery) {
                    $dishesQuery->whereRaw('1 = 0');
                }
            }

            $productsQuery->where(function ($sub) use ($searchTerm, $isStrictSearch, $isColombianSearch, $isIvaSearch) {
                if ($isColombianSearch) {
                    $sub->where('products.is_colombian_origin', 1);
                } elseif ($isIvaSearch) {
                    $sub->where('products.iva', 1);
                } else {
                    $words = $isStrictSearch ? [$searchTerm] : explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $sub->where(function ($wq) use ($word) {
                            $wq->where('products.name', 'like', "%{$word}%")
                                ->orWhere('products.active_ingredient', 'like', "%{$word}%")
                                ->orWhere('laboratories.name', 'like', "%{$word}%");
                        });
                    }
                }
            });

            if (!$isColombianSearch && !$isIvaSearch) {
                $packsQuery->where(function ($sub) use ($searchTerm, $isStrictSearch) {
                    $words = $isStrictSearch ? [$searchTerm] : explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $sub->where('product_packs.name', 'like', "%{$word}%");
                    }
                });

                if ($dishesQuery) {
                    $dishesQuery->where(function ($sub) use ($searchTerm, $isStrictSearch) {
                        $words = $isStrictSearch ? [$searchTerm] : explode(' ', $searchTerm);
                        foreach ($words as $word) {
                            $sub->where('dishes.name', 'like', "%{$word}%");
                        }
                    });
                }
            }
        }

        // ── 6. Filtros adicionales ──
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
            $productsQuery->whereRaw(
                'COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0 AND pl.expiration_date >= CURDATE()), 0) > 0'
            );
            $packsQuery->where('product_packs.max_quantity', '>', 0);
            if ($dishesQuery) {
                $dishesQuery->whereRaw('1 = 1');
            }
        } elseif ($hasStock === false) {
            $productsQuery->whereRaw(
                'COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.quantity > 0 AND pl.expiration_date >= CURDATE()), 0) = 0'
            );
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

    // -------------------------------------------------------------------------
    // Ordenamiento
    // -------------------------------------------------------------------------

    private function applySortingProduct(QueryBuilder $query, ?string $sortBy, string $orderBy): QueryBuilder
    {
        // Prioridad fija: packs primero, luego items con descuento, luego el resto
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

    // -------------------------------------------------------------------------
    // API pública — el controlador llama SOLO estas dos funciones
    // -------------------------------------------------------------------------

    /**
     * Normaliza los filtros del Request en un array limpio.
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q'             => $request->q,
            'categoryId'    => $request->categoryId,
            'laboratoryId'  => $request->laboratoryId,
            'originId'      => $request->originId,
            'hasStock'      => $request->has('hasStock')
                ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN)
                : null,
            'groupId'       => $request->get('groupId'),
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Retorna el query base (UNION) envuelto en un subquery para contar.
     * No tiene ORDER BY — solo sirve para COUNT(*).
     */
    public function getCountQueryProduct(Request $request): QueryBuilder
    {
        $unionQuery = $this->getBaseQueryProduct($this->filtersFromRequest($request));
        $sql        = $unionQuery->toSql();
        $bindings   = $unionQuery->getBindings();

        return DB::table(DB::raw("({$sql}) as tpv_count"))
            ->setBindings($bindings);
    }

    /**
     * Retorna el query paginable con ordenamiento aplicado.
     */
    public function getFilteredQuery(Request $request): QueryBuilder
    {
        $unionQuery = $this->getBaseQueryProduct($this->filtersFromRequest($request));

        $query = DB::table(DB::raw("({$unionQuery->toSql()}) as tpv_items"))
            ->mergeBindings($unionQuery)
            ->select('*');

        $this->applySortingProduct(
            $query,
            $request->input('sortBy'),
            $request->input('orderBy', 'asc')
        );

        return $query;
    }
}
