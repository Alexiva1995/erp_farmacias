<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Query\Builder as QueryBuilder;

class OrderQueryService
{

    private function getBaseQuery($valor, ?string $startDate = null, ?string $endDate = null): Builder
    {
        if ($valor == 'Completed') {
            $query = Order::query()->where('status', $valor)->with('client', 'seller');
            // Si no hay rango de fechas, usar solo el día actual (comportamiento por defecto)
            if (empty($startDate) && empty($endDate)) {
                $start = now()->startOfDay();
                $end = now()->endOfDay();
                return $query->whereBetween('order_date', [$start, $end]);
            }
            return $query;
        } elseif ($valor == 'all') {
            return Order::query()->with('client', 'seller');
        }

        return Order::query()->where('status', $valor)->with('client', 'seller');
    }


    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";

            $query->where(function ($subQuery) use ($searchTerm) {
                // Búsqueda en la relación 'client' por identificación
                $subQuery->whereHas('client', function ($clientQuery) use ($searchTerm) {
                    $clientQuery->where('identification', 'like', $searchTerm);
                });

                // Búsqueda en la relación 'seller' por username
                $subQuery->orWhereHas('seller', function ($sellerQuery) use ($searchTerm) {
                    $sellerQuery->where('username', 'like', $searchTerm);
                });
            });
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (!empty($filters['state'])) {
            $query->where('status', $filters['state']);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
            $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

            if ($startDate && $endDate) {
                $query->whereBetween('order_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('order_date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('order_date', '<=', $endDate);
            }
        }

        return $query;
    }

    public function getFilteredQuery(Request $request, $valor): Builder
    {
        $query = $this->getBaseQuery(
            $valor,
            $request->input('start_date'),
            $request->input('end_date')
        );
        $filters = [
            'id' => $request->id,
            'q' => $request->q,
            'currency' => $request->currency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'state' => $request->state,
            'seller_id' => $request->seller_id,
        ];
        $this->applyFilters($query, $filters);
        $this->applyOrderSorting(
            $query,
            $request->input('sortBy'),
            $request->input('orderBy', 'desc')
        );
        return $query;
    }

    private function applyOrderSorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('orders.id', 'desc');
        }

        $sortDir = strtolower($orderBy) === 'asc' ? 'asc' : 'desc';

        $directColumns = [
            'id' => 'orders.id',
            'total_amount' => 'orders.total_amount',
            'currency' => 'orders.currency',
            'date' => 'orders.order_date',
            'status' => 'orders.status',
        ];

        if (isset($directColumns[$sortBy])) {
            return $query->orderBy($directColumns[$sortBy], $sortDir);
        }

        switch ($sortBy) {
            case 'identification':
                return $query->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                    ->orderBy('clients.identification', $sortDir)
                    ->select('orders.*');
            case 'client_full_name':
                return $query->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                    ->orderBy('clients.name', $sortDir)
                    ->orderBy('clients.last_name', $sortDir)
                    ->select('orders.*');
            case 'seller.username':
                return $query->leftJoin('users', 'orders.seller_id', '=', 'users.id')
                    ->orderBy('users.username', $sortDir)
                    ->select('orders.*');
            default:
                return $query->orderBy('orders.id', 'desc');
        }
    }


    private function getBaseQueryProduct(array $filters = []): QueryBuilder
    {
        $resourceService = app(\App\Services\Resources\ResourceService::class);
        $tasaBs = $resourceService->getExchangeRate('EUR') ?: 1;
        $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;

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
                DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE() AND product_lots.quantity > 0) as next_expiration'),
                DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) as valid_stock_sum'),
                DB::raw("(
                    SELECT GREATEST(
                        COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0),
                        COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0),
                        COALESCE((SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0 AND TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1), 0)
                    )
                ) as discount_percentage"),
                DB::raw("(
                    SELECT CASE
                        WHEN COALESCE((SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0 AND TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1), 0) >= GREATEST(
                            COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0),
                            COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0)
                        ) AND (SELECT eo.discount_percentage FROM expiration_offers eo WHERE eo.is_active = 1 AND EXISTS (SELECT 1 FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0 AND TIMESTAMPDIFF(MONTH, CURDATE(), pl.expiration_date) <= eo.months_to_expiration) ORDER BY eo.discount_percentage DESC LIMIT 1) > 0
                        THEN 'expiration'
                        WHEN COALESCE((SELECT io.discount_percent FROM individual_offers io WHERE io.product_id = products.id AND io.start_date <= CURDATE() AND io.end_date >= CURDATE() ORDER BY io.discount_percent DESC LIMIT 1), 0) >=
                             COALESCE((SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1), 0)
                        THEN 'individual'
                        WHEN (SELECT co.discount_percentage FROM category_offers co WHERE co.category_id = products.category_id AND co.is_active = 1 AND co.start_date <= CURDATE() AND co.end_date >= CURDATE() ORDER BY co.discount_percentage DESC LIMIT 1) > 0
                        THEN 'category'
                        ELSE NULL
                    END
                ) as discount_type"),
            ])
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')->orWhere('products.is_deleted', 0);
            });

        // 2. Consulta de PACKS
        $packsQuery = DB::table('product_packs')
            ->select([
                'product_packs.id',
                'product_packs.name',
                'product_packs.total_price as sale_price',
                DB::raw("ROUND(product_packs.total_price * {$tasaBs}, 2) as price_bs"),
                DB::raw("ROUND(product_packs.total_price * {$tasaCop}, 2) as price_cop"),
                DB::raw("(
                SELECT GROUP_CONCAT(
                    CONCAT(p.name, ' [', COALESCE(p.active_ingredient, 'S/I'), ' - ', COALESCE(l.name, 'S/L'), ']') 
                    SEPARATOR ' | '
                )
                FROM products p
                LEFT JOIN laboratories l ON p.laboratory_id = l.id
                WHERE JSON_CONTAINS(
                    JSON_KEYS(product_packs.pack_config), 
                    CAST(JSON_QUOTE(CAST(p.id AS CHAR)) AS JSON)
                )
            ) as active_ingredient"),
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
            ->where(function ($q) {
                $q->whereNull('product_packs.max_sale_date')
                    ->orWhere('product_packs.max_sale_date', '>=', now()->toDateString());
            });

        // APLICAR BUSCADOR 'Q' A AMBOS LADOS
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $searchTermLower = strtolower(trim($searchTerm));
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            // Detectar búsquedas especiales por "col" o "(g)"
            $isColombianSearch = in_array($searchTermLower, ['col', '(col)', 'colombiano', 'colombianos']);
            $isIvaSearch = in_array($searchTermLower, ['g', '(g)', 'iva', 'gravado']);

            // Si es búsqueda especial por "col" o "(g)", excluir packs
            if ($isColombianSearch || $isIvaSearch) {
                $packsQuery->whereRaw('1 = 0');
            }

            // Filtro para PRODUCTOS
            $productsQuery->where(function ($subQuery) use ($searchTerm, $isStrictSearch, $isColombianSearch, $isIvaSearch) {
                // Si es búsqueda por colombianos
                if ($isColombianSearch) {
                    $subQuery->where('products.is_colombian_origin', 1);
                }
                // Si es búsqueda por IVA
                elseif ($isIvaSearch) {
                    $subQuery->where('products.iva', 1);
                }
                // Búsqueda normal
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
        }

        // APLICAR OTROS FILTROS (Solo a productos)
        if (!empty($filters['laboratoryId'])) {
            $productsQuery->where('products.laboratory_id', $filters['laboratoryId']);
            // Esto hace que si filtras por laboratorio, los packs no salgan (porque no tienen laboratorio)
            $packsQuery->whereRaw('1 = 0');
        }

        if (!empty($filters['originId'])) {
            $productsQuery->where('origin_id', $filters['originId']);
            $packsQuery->whereRaw('1 = 0');
        }

        if (!empty($filters['groupId'])) {
            $productsQuery->where('products.group_id', $filters['groupId']);
            $packsQuery->whereRaw('1 = 0');
        }


        $hasStock = $filters['hasStock'] ?? null;
        if ($hasStock === true) {
            $productsQuery->whereRaw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) > 0');
            $packsQuery->where('product_packs.max_quantity', '>', 0);
        } elseif ($hasStock === false) {
            $productsQuery->whereRaw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) = 0');
            $packsQuery->where('product_packs.max_quantity', '=', 0);
            ;
        }

        return $productsQuery->unionAll($packsQuery);
    }

    /* private function getBaseQueryProduct(): QueryBuilder
     {
         $resourceService = app(\App\Services\Resources\ResourceService::class);
         $tasaBs = $resourceService->getExchangeRate('BS') ?: 1;
         $tasaCop = $resourceService->getExchangeRate('COP') ?: 1;

         // 1. Definimos la consulta de PRODUCTOS
     $productsQuery = DB::table('products')
         ->select([
             'products.id',
             'products.name',
             'products.sale_price',
             DB::raw("ROUND(products.sale_price * {$tasaBs}, 2) as price_bs"),
             DB::raw("ROUND(products.sale_price * {$tasaCop}, 2) as price_cop"),
             'products.active_ingredient',
             'products.laboratory_id',                          
             'products.group_id',
             'laboratories.name as laboratory_name', // Join manual para evitar el 'with' que rompe el union
             DB::raw("'product' as item_type"), // Diferenciador
             DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) as valid_stock_sum')
         ])
         ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
         ->whereNull('products.deleted_at');

     // 2. Definimos la consulta de PACKS
     $packsQuery = DB::table('product_packs')
         ->select([
             'product_packs.id',
             'product_packs.name',
             'product_packs.total_price as sale_price',
             DB::raw("ROUND(product_packs.total_price * {$tasaBs}, 2) as price_bs"),
             DB::raw("ROUND(product_packs.total_price * {$tasaCop}, 2) as price_cop"),
             DB::raw("'' as active_ingredient"), // Los packs no suelen tener este campo, lo enviamos vacío
             DB::raw('NULL as laboratory_id'),
             DB::raw('NULL as group_id'),
             DB::raw("'Pack Promocional' as laboratory_name"),
             DB::raw("'pack' as item_type"), // Diferenciador
             DB::raw('999 as valid_stock_sum') // Stock ficticio o lógica de stock de packs
         ])
         ->whereNull('product_packs.max_sale_date');

     // 3. Unificamos
     return $productsQuery->union($packsQuery);

        /* return Product::query()->select(
             'products.*'
         )
             ->with([
                 'laboratory',
                 'origin',
                 'group',
             ])
             ->addSelect(DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) as valid_stock_sum'));*/
    /* }*/

    /* private function applyFiltersProduct(Builder $query, array $filters): Builder
     {
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

         if (!empty($filters['originId'])) {
             $query->where('origin_id', $filters['originId']);
         }

         if (!empty($filters['groupId'])) {
             $query->where('group_id', $filters['groupId']);
         }

         $hasStock = $filters['hasStock'] ?? null;
         if ($hasStock === true) {
             $query->groupBy('products.id')
                 ->havingRaw('valid_stock_sum > 0');
         } elseif ($hasStock === false) {
             $query->groupBy('products.id')
                 ->havingRaw('valid_stock_sum <= 0');
         }

         if (!empty($filters['groupId'])) {
             $query->where('group_id', $filters['groupId']);
         }

         return $query;
     }*/

    private function applyFiltersProduct($query, array $filters)
    {
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $subQuery->where('products.name', 'like', "%{$searchTerm}%")
                        ->orWhere('products.active_ingredient', 'like', "%{$searchTerm}%");
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $subQuery->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('products.name', 'like', "%{$word}%")
                                ->orWhere('products.active_ingredient', 'like', "%{$word}%")
                                // Como usamos UNION, laboratory_name ya debe venir calculado en el select
                                ->orWhere('laboratories.name', 'like', "%{$word}%");
                        });
                    }
                }
            });
        }

        // Filtros que solo afectan a productos (evitamos que rompan los packs)
        if (!empty($filters['laboratoryId'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('laboratory_id', $filters['laboratoryId'])
                    ->orWhere('item_type', 'pack'); // Permitimos que los packs sigan pasando
            });
        }

        // Lógica de Stock
        $hasStock = $filters['hasStock'] ?? null;
        if ($hasStock === true) {
            $query->where('valid_stock_sum', '>', 0);
        } elseif ($hasStock === false) {
            $query->where('valid_stock_sum', '<=', 0);
        }

        if (!empty($filters['groupId'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('group_id', $filters['groupId']);
                // Si quieres que los packs desaparezcan al filtrar grupos, quita la línea de abajo
                //->orWhere('item_type', 'pack'); 
            });
        }

        return $query;
    }

    private function applySortingProduct($query, ?string $sortBy, string $orderBy)
    {
        // Siempre ordenar primero por item_type para que los packs aparezcan primero
        // Usamos CASE para que 'pack' = 0 y 'product' = 1, así los packs van primero
        $query->orderByRaw("CASE WHEN item_type = 'pack' THEN 0 ELSE 1 END ASC");

        // Si no hay orden especificado, ordenamos por el nombre normalizado
        if (empty($sortBy)) {
            return $query->orderBy('name', 'asc');
        }

        switch ($sortBy) {
            case 'laboratory.name':
                return $query->orderBy('laboratory_name', $orderBy);
            case 'valid_stock':
            case 'lots_sum_quantity':
            case 'valid_stock_sum':
                return $query->orderBy('valid_stock_sum', $orderBy);
            case 'name':
                return $query->orderBy($sortBy, $orderBy);
            case 'sale_price':
            case 'price':
                return $query->orderBy('sale_price', $orderBy);
            case 'sales_average':
                return $query->orderBy('sales_average', $orderBy);
            case 'next_expiration':
                return $query->orderBy('next_expiration', $orderBy);
                /**
                 * Nota: Para los packs, este valor será NULL o muy lejano.
                 * El ordenamiento funcionará basándose en el alias que definas en el select
                 * si decides agregar el vencimiento al UNION.
                 */
                return $query->orderBy('name', $orderBy); // Opcional: fallback por nombre

            default:
                // Intentamos ordenar por la columna directa si existe en el resultado unificado
                return $query->orderBy('name', 'asc');
        }
    }

    /*   private function applySortingProduct(Builder $query, ?string $sortBy, string $orderBy): Builder
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
               case 'valid_stock_sum':
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
       }*/

    public function getFilteredQueryProduct(Request $request): QueryBuilder
    {

        //  $query = $this->getBaseQueryProduct();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'groupId' => $request->get('groupId'),
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $query = $this->getBaseQueryProduct($filters);

        //$this->applyFiltersProduct($query, $filters);
        $this->applySortingProduct($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
    public function getDebitoFiscal(string $startDate, string $endDate): array
    {
        try {
            // Consultar tabla fiscal_history para registros con iva_amount
            $query = DB::table('fiscal_history')
                ->whereNotNull('iva_amount')
                ->where('iva_amount', '>', 0)
                ->whereBetween('invoice_date', [$startDate, $endDate]);

            $fiscalRecords = $query->get();

            // Calcular totales
            $totalIvaAmount = $fiscalRecords->sum('iva_amount');
            $totalSpeAmount = $fiscalRecords->sum('spe_amount') ?? 0; // Si existe campo SPE

            // El débito fiscal es la suma del IVA cobrado en ventas
            $totalDebito = $totalIvaAmount + $totalSpeAmount;

            Log::info('Cálculo débito fiscal:', [
                'periodo' => [$startDate, $endDate],
                'total_records' => $fiscalRecords->count(),
                'total_iva_amount' => $totalIvaAmount,
                'total_spe_amount' => $totalSpeAmount,
                'total_debito' => $totalDebito
            ]);

            return [
                'total_records' => $fiscalRecords->count(),
                'total_iva_amount' => $totalIvaAmount,
                'total_spe_amount' => $totalSpeAmount,
                'total_debito' => $totalDebito,
                'records' => $fiscalRecords
            ];

        } catch (\Exception $e) {
            Log::error('Error en getDebitoFiscal: ' . $e->getMessage());
            throw $e;
        }
    }
    public function getFiscalHistoryRecords(string $startDate, string $endDate, int $page = 1, int $itemsPerPage = 10): array
    {
        try {
            // Query base para fiscal_history con IVA
            $query = DB::table('fiscal_history')
                ->whereNotNull('iva_amount')
                ->where('iva_amount', '>', 0)
                ->whereBetween('invoice_date', [$startDate, $endDate])
                ->orderBy('invoice_date', 'desc')
                ->orderBy('order_id', 'desc');

            // Clonar query para el conteo total
            $totalQuery = clone $query;
            $totalRecords = $totalQuery->count();

            // Aplicar paginación
            $offset = ($page - 1) * $itemsPerPage;
            $records = $query
                ->skip($offset)
                ->take($itemsPerPage)
                ->get();

            // Formatear los registros para el frontend
            $formattedRecords = $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'order_id' => $record->order_id,
                    'invoice_number' => $record->invoice_number,
                    'identification' => $record->identification,
                    'business_name' => $record->business_name,
                    'address' => $record->address,
                    'exempt_amount' => (float) $record->exempt_amount,
                    'iva_amount' => (float) $record->iva_amount,
                    'total_amount' => (float) $record->total_amount,
                    'invoice_date' => $record->invoice_date,
                    'spe' => (bool) $record->spe,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at
                ];
            });

            // Calcular totales para la página actual
            $pageTotals = [
                'total_exempt' => $formattedRecords->sum('exempt_amount'),
                'total_iva' => $formattedRecords->sum('iva_amount'),
                'total_amount' => $formattedRecords->sum('total_amount')
            ];

            Log::info('Registros de fiscal history obtenidos:', [
                'periodo' => [$startDate, $endDate],
                'page' => $page,
                'items_per_page' => $itemsPerPage,
                'total_records' => $totalRecords,
                'records_in_page' => $formattedRecords->count(),
                'page_totals' => $pageTotals
            ]);

            return [
                'data' => $formattedRecords->toArray(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $itemsPerPage,
                    'total' => $totalRecords,
                    'last_page' => ceil($totalRecords / $itemsPerPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $itemsPerPage, $totalRecords)
                ],
                'totals' => $pageTotals,
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error en getFiscalHistoryRecords: ' . $e->getMessage());
            throw $e;
        }
    }
}
