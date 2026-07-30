<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository
{

    private $subConsultaParaCalcularStockPorLotes = 'COALESCE(products.stock, 0)';

    public function consultProductById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function consultarTodosLosProductOrdenaPor($sortBy = "name", $orderBy = "ASC")
    {
        return Product::query()->select(['id', 'name'])->orderBy($sortBy, $orderBy)->get();
    }

    public function builerFiltrarProductforStock($filtros): Builder
    {
        $viewType = $filtros["viewType"] ?? "individual";
        $isGroup = $viewType === "group";

        $days = $filtros["days"] ?? 30;
        $timeZone = new \DateTimeZone(config("app.timezone"));
        $dateToday = new \DateTime("now", $timeZone);
        $previousDate = new \DateTime("now", $timeZone);
        $previousDate->modify("-" . $days . " days");
        
        $dateTodayStr = $filtros["dateToday"] ?? $dateToday->format("Y-m-d H:i:s");
        $previousDateStr = $filtros["previousDate"] ?? $previousDate->format("Y-m-d");

        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
        if ($isRestaurant) {
            $salesSubquery = DB::table('inventory_movements')
                ->select('product_id', DB::raw('COALESCE(ABS(SUM(quantity)), 0) as total_sold'))
                ->where('quantity', '<', 0)
                ->whereBetween('created_at', [$previousDateStr, $dateTodayStr])
                ->groupBy('product_id');
        } else {
            $salesSubquery = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->select('order_details.product_id', DB::raw('COALESCE(SUM(order_details.quantity), 0) as total_sold'))
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', [$previousDateStr, $dateTodayStr])
                ->groupBy('order_details.product_id');
        }

        $aoSubquery = DB::table('auto_order_details as aod')
            ->join('auto_orders as ao', 'ao.id', '=', 'aod.order_id')
            ->select('aod.product_id', DB::raw('COALESCE(SUM(aod.quantity), 0) as total_ao'))
            ->whereIn('ao.status', [0, 1])
            ->where('aod.status', 0)
            ->whereNull('ao.deleted_at')
            ->whereNull('aod.deleted_at')
            ->groupBy('aod.product_id');

        $subqueryStockLotes = 'COALESCE(products.stock, 0)';
        $subqueryTotalSold = 'COALESCE(sales_agg.total_sold, 0)';
        $subqueryAO = 'COALESCE(ao_agg.total_ao, 0)';
        $prefPorcentajeSql = '100';

        $lapso = $filtros["lapso_de_tiempo"] ?? $filtros["days"] ?? 30;
        if (!is_string($lapso)) $lapso = $lapso . " days";

        if ($isRestaurant) {
            $baseAverage = '(SELECT COALESCE(ABS(SUM(im_avg.quantity)), 0) / COALESCE(LEAST(12.0, GREATEST(1.0, TIMESTAMPDIFF(DAY, (SELECT MIN(created_at) FROM daily_closures), NOW()) / 30.0)), 1.0) 
                            FROM inventory_movements im_avg 
                            WHERE im_avg.product_id = products.id 
                            AND im_avg.quantity < 0 
                            AND im_avg.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH))';
        } else {
            $baseAverage = 'products.sales_average';
        }

        if (str_contains($lapso, "7")) {
            $promedioCalculadoSql = '(' . $baseAverage . ') / 4';
        } elseif (str_contains($lapso, "15")) {
            $promedioCalculadoSql = '(' . $baseAverage . ') / 2';
        } elseif (str_contains($lapso, "60")) {
            $promedioCalculadoSql = '(' . $baseAverage . ') * 2';
        } elseif (str_contains($lapso, "90")) {
            $promedioCalculadoSql = '(' . $baseAverage . ') * 3';
        } else {
            $promedioCalculadoSql = $baseAverage;
        }

        if ($isGroup) {
            $columnas = [
                DB::raw('MIN(products.id) as id'),
                DB::raw('MAX(COALESCE(groups_products.name, CONCAT("INDIVIDUAL: ", products.name))) as name'),
                DB::raw('MAX(products.group_id) as group_id'),
                DB::raw('MAX(products.photo_url) as photo_url'),
                DB::raw('SUM(' . $subqueryStockLotes . ') as lote_quantity'),
                DB::raw('SUM(' . $subqueryTotalSold . ') as total_sold_completed'),
                DB::raw('100 as preferencia_product'),
                DB::raw('SUM(' . $promedioCalculadoSql . ') as promedio_calculado'),
                DB::raw('SUM(' . $subqueryAO . ') as totalQuantityInAutoOrder'),
                DB::raw('MAX(products.unit_cost) as unit_cost'),
                DB::raw('MAX(products.laboratory_id) as laboratory_id'),
                DB::raw('MAX(products.presentation) as presentation'),
                DB::raw('MAX(products.unit_of_measure) as unit_of_measure'),
            ];
        } else {
            $columnas = [
                'products.id',
                'products.name',
                'products.group_id',
                'products.photo_url',
                'products.laboratory_id',
                'products.unit_cost',
                'products.active_ingredient',
                'products.is_colombian_origin',
                'products.presentation',
                'products.unit_of_measure',
                DB::raw($subqueryStockLotes . ' AS lote_quantity'),
                DB::raw($subqueryTotalSold . ' AS total_sold_completed'),
                DB::raw($prefPorcentajeSql . ' AS preferencia_product'),
                DB::raw($promedioCalculadoSql . ' AS promedio_calculado'),
                DB::raw($subqueryAO . ' AS totalQuantityInAutoOrder'),
            ];
        }

        $tipoFiltracion = $filtros["tipo_filtracion"] ?? "average";
        $stockEfectivo = '(' . $subqueryStockLotes . ' + ' . $subqueryAO . ')';
        
        if ($tipoFiltracion == "sales") {
            $calcDiff = '(' . $stockEfectivo . ') - (' . $subqueryTotalSold . ')';
        } elseif ($tipoFiltracion == "combinado") {
            $calcDiff = '(' . $stockEfectivo . ') - (((' . $subqueryTotalSold . ') + (' . $promedioCalculadoSql . ')) / 2)';
        } else {
            $calcDiff = '(' . $stockEfectivo . ') - (' . $promedioCalculadoSql . ')';
        }

        if ($isGroup) {
            $columnas[] = DB::raw("SUM(CASE 
                WHEN ($calcDiff) > 0 THEN CEIL($calcDiff) 
                ELSE FLOOR($calcDiff) 
            END) AS diferencia_product");
        } else {
            $columnas[] = DB::raw("CASE 
                WHEN ($calcDiff) > 0 THEN CEIL($calcDiff) 
                ELSE FLOOR($calcDiff) 
            END AS diferencia_product");
        }

        // Construcción de la Consulta
        $consulta = Product::select($columnas)
            ->leftJoinSub($salesSubquery, 'sales_agg', 'sales_agg.product_id', '=', 'products.id')
            ->leftJoinSub($aoSubquery, 'ao_agg', 'ao_agg.product_id', '=', 'products.id');

        if ($isGroup) {
            $consulta->leftJoin('groups_products', 'products.group_id', '=', 'groups_products.id')
                ->groupBy(DB::raw('COALESCE(products.group_id, CONCAT("p_", products.id))'));
        }

        $consulta->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->with(["laboratory", "lots"]);

        // Filtros adicionales
        if (array_key_exists("q", $filtros) && $filtros["q"] != "") {
            $isStrictSearch = $filtros["isStrictSearch"] ?? false;
            $searchTerm = $filtros["q"];

            $consulta->where(function ($query) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $query->where("products.name", "like", "%" . $searchTerm . "%")
                        ->orWhere("products.active_ingredient", "like", "%" . $searchTerm . "%")
                        ->orWhere("products.id", "like", $searchTerm);
                } else {
                    $words = explode(' ', trim($searchTerm));
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (empty($word)) continue;
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where("products.name", "like", "%" . $word . "%")
                                ->orWhere("products.active_ingredient", "like", "%" . $word . "%")
                                ->orWhere("products.id", "like", "%" . $word . "%");
                        });
                    }
                }
            });
        }

        if (array_key_exists("laboratoryId", $filtros) && $filtros["laboratoryId"]) {
            $consulta->where("products.laboratory_id", "=", $filtros["laboratoryId"]);
        }

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->having("lote_quantity", ">", 0);
            } else {
                $consulta->having("lote_quantity", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros)) {
            if ($filtros["stock"] == "exceso") {
                $consulta->having("diferencia_product", ">", 0);
            } elseif ($filtros["stock"] == "fallas") {
                $consulta->having("diferencia_product", "<", 0);
            }
        }

        if (array_key_exists("isColombian", $filtros)) {
            if ($filtros["isColombian"] == true || $filtros["isColombian"] === "true") {
                $consulta->where("products.is_colombian_origin", "=", 1);
            } elseif ($filtros["isColombian"] === false || $filtros["isColombian"] === "false") {
                $consulta->where(function ($q) {
                    $q->where("products.is_colombian_origin", "=", 0)
                      ->orWhereNull("products.is_colombian_origin");
                });
            }
        }

        if (array_key_exists("isNovaventa", $filtros)) {
            if ($filtros["isNovaventa"] == true || $filtros["isNovaventa"] === "true") {
                $consulta->where("products.is_novaventa", "=", 1);
            } elseif ($filtros["isNovaventa"] === false || $filtros["isNovaventa"] === "false") {
                $consulta->where(function ($q) {
                    $q->where("products.is_novaventa", "=", 0)
                      ->orWhereNull("products.is_novaventa");
                });
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $sortCol = $filtros["sortBy"];
            $sortDir = strtolower($filtros["orderBy"]) === 'desc' ? 'desc' : 'asc';
            if ($sortCol === 'stock') $sortCol = 'lote_quantity';
            $consulta->orderBy($sortCol, $sortDir);
        } else {
            $consulta->orderBy("diferencia_product", "DESC");
        }

        return $consulta;
    }

    public function filtrarProductforStocktWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $viewType = $filtros["viewType"] ?? "individual";
        $isGroup = $viewType === "group";

        $consulta = $this->builerFiltrarProductforStock($filtros);
        $paginacion = $consulta->paginate($perPage);

        if ($isGroup && $paginacion->count() > 0) {
            $groupIds = $paginacion->pluck('group_id')->filter()->toArray();
            $productIds = $paginacion->whereNull('group_id')->pluck('id')->toArray();

            $filtrosHijos = $filtros;
            $filtrosHijos['viewType'] = 'individual';
            
            $consultaHijos = $this->builerFiltrarProductforStock($filtrosHijos);
            
            $consultaHijos->where(function($q) use ($groupIds, $productIds) {
                if (!empty($groupIds)) $q->whereIn('products.group_id', $groupIds);
                if (!empty($productIds)) $q->orWhereIn('products.id', $productIds);
            });

            $hijos = $consultaHijos->get()->groupBy(function($item) {
                return $item->group_id ? "g_" . $item->group_id : "p_" . $item->id;
            });

            $paginacion->getCollection()->transform(function($grupo) use ($hijos) {
                $key = $grupo->group_id ? "g_" . $grupo->group_id : "p_" . $grupo->id;
                $grupo->productos = $hijos->get($key, collect([]));
                return $grupo;
            });
        }

        return $paginacion;
    }

    public function filtrarProductforStocktWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarProductforStock($filtros);
        return $consulta->get();
    }

    public function builerFiltrarProductForIaOrderAssistantTypeSales($filtros): Builder
    {
        // solicitar = stock - ventas individuales

        $ventasIndividualDelProducto = '
            (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            )
        
        ';

        $iaSolicitar = 'CASE 
                WHEN (' . $ventasIndividualDelProducto . ' - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
                )) > 0 THEN CEIL(' . $ventasIndividualDelProducto . ' - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
                ))
                ELSE FLOOR(' . $ventasIndividualDelProducto . ' - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
                ))
            END';

        $columnas = [
            'products.id',
            'products.name',
            DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM product_lots WHERE product_lots.product_id = products.id ) as stock'),
            'products.group_id',
            'products.laboratory_id',
            'products.barcode',
            "products.sales_average",
            "products.sale_price",
            "products.unit_cost",
            "products.psychotropic",
            "products.is_colombian_origin",
            "products.is_ordered",
            "products.active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(' . $this->subConsultaParaCalcularStockPorLotes . ') AS lote_quantity'),
            DB::raw('(
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            ) AS total_sold_completed'),
            DB::raw('(
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = "Completed"
                AND p.is_scarce = 0 AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) AS total_group_sales'),
            //  para calcular la preferencia del producto veta del producto / ventas totales del grupo que pertenece el producto * 100
            DB::raw(' NULLIF((
            (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            ) / (
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = "Completed"
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) 
            ),0)* 100 AS preferencia_product'),
            DB::raw('(
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            ) AS totalQuantityInAutoOrder'),
            DB::raw('(' . $iaSolicitar . ') AS solicitar'),
            DB::raw('(
                SELECT ps.barcode_match
                FROM product_suppliers ps
                WHERE ps.product_id = products.id
                  AND ps.barcode_match IS NOT NULL
                  AND ps.barcode_match != \'\'
                  AND ps.unit_cost_usd > 0
                ORDER BY ps.unit_cost_usd ASC
                LIMIT 1
            ) AS cheapest_barcode'),
            'products.unit_cost as current_unit_cost',
            'products.manual_solicitar',
            DB::raw('(
                SELECT ps.unit_cost_usd
                FROM product_suppliers ps
                WHERE ps.product_id = products.id
                  AND ps.unit_cost_usd > 0
                ORDER BY ps.unit_cost_usd ASC
                LIMIT 1
            ) AS best_supplier_price'),
        ];

        // calcular promedio en base a los dias => promedio_calculado
        $lapso = $filtros["lapso_de_tiempo"] ?? "1 month";
        if ($lapso == "7 days") {
            $columnas[] = DB::raw('sales_average / 4 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 4';
        } elseif ($lapso == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        } elseif ($lapso == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        } elseif ($lapso == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        } elseif ($lapso == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        } elseif ($lapso == "1 year") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        } else {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        // demanda_ponderada = (promedio + ventas) / 2  (antes de restar stock/AO)
        $columnas[] = DB::raw('((' . $promedio_calculado . ' + ' . $ventasIndividualDelProducto . ') / 2) AS demanda_ponderada');

        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"])->where('is_deleted', false)->where('is_scarce', false);

        if (array_key_exists("ids_in", $filtros) && !empty($filtros["ids_in"])) {
            $consulta->whereIn("products.id", $filtros["ids_in"]);
        }

        if (array_key_exists("ids", $filtros)) {
            $consulta->whereIn("id", $filtros["ids"]);
        }

        // if (array_key_exists("sin_proveedor", $filtros)) {
        //     $consulta->doesntHave("productSuppliers");
        // }

        if (array_key_exists("tipo_vista", $filtros)) {
            if ($filtros["tipo_vista"] == true) {
                $consulta->join("groups_products", "products.group_id", "=", "groups_products.id")
                    ->orderBy("groups_products.name", "ASC");
            }
        }

        if (array_key_exists("q", $filtros)) {
            if ($filtros["q"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["q"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["q"] . "%");
                });
            }
        }


        if (array_key_exists("laboratoryId", $filtros)) {
            if (count($filtros["laboratoryId"]) > 0) {
                $consulta->whereIn("laboratory_id", $filtros["laboratoryId"]);
            }
        }

        if (array_key_exists("groups", $filtros)) {
            if (count($filtros["groups"]) > 0) {
                $consulta->whereIn("group_id", $filtros["groups"]);
            }
        }

        if (array_key_exists("is_ordered", $filtros)) {
            $consulta->where("is_ordered", "=", $filtros["is_ordered"]);
        }

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->having("lote_quantity", ">", 0);
            } else {
                $consulta->having("lote_quantity", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros) && $filtros["stock"] !== 'all') {

            if ($filtros["stock"] == "exceso") {
                // Ahora unificado: demanda - stock - AO < 0 = exceso
                $consulta->having("solicitar", "<", 0);
            }
            if ($filtros["stock"] == "fallas") {
                // Ahora unificado: demanda - stock - AO > 0 = falla puramente matemático
                $consulta->having("solicitar", ">", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
            });
        }

        if (array_key_exists("isColombian", $filtros)) {
            if ($filtros["isColombian"] == true || $filtros["isColombian"] === "true") {
                $consulta->where("is_colombian_origin", "=", 1);
            }
        }

        if (array_key_exists("isNovaventa", $filtros)) {
            if ($filtros["isNovaventa"] == true || $filtros["isNovaventa"] === "true") {
                $consulta->where("is_novaventa", "=", 1);
            } else {
                $consulta->where(function ($q) {
                    $q->where("is_novaventa", "=", 0)
                      ->orWhereNull("is_novaventa");
                });
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("solicitar", "DESC");
        }


        return $consulta;
    }

    public function filtrarProductforIaOrderAssistantTypeSalesWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarProductForIaOrderAssistantTypeSales($filtros);

        return $consulta->get();
    }

    public function filtrarProductforIaOrderAssistantTypeSalesWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarProductForIaOrderAssistantTypeSales($filtros);

        return $consulta->paginate($perPage);
    }





    // public function consultarProductosSinProveedor()
    // {
    //     $consulta = Product::query()
    //         ->with(["laboratory", "lots", "group", "productSuppliers"])
    //         ->doesntHave("productSuppliers")
    //         ->get();

    //     return $consulta;
    // }
    public function builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros): Builder
    {
        $columnas = [
            'products.id',
            'products.name',
            DB::raw('(' . $this->subConsultaParaCalcularStockPorLotes . ') as stock'),
            'products.group_id',
            'products.laboratory_id',
            'products.barcode',
            "products.is_ordered",
            "products.sales_average",
            "products.sale_price",
            "products.unit_cost",
            "products.psychotropic",
            "products.is_colombian_origin",
            "products.is_unified_group",
            "products.active_ingredient",
            'products.manual_solicitar',
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(' . $this->subConsultaParaCalcularStockPorLotes . ') AS lote_quantity'),
            DB::raw('(
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            ) AS total_sold_completed'),
            DB::raw('(
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = "Completed"
                AND p.is_scarce = 0
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) AS total_group_sales'),
            // Agregar esta línea para sumar los sales_average por group_id
            DB::raw('SUM(CASE WHEN is_scarce = 0 THEN sales_average ELSE 0 END) OVER (PARTITION BY group_id) AS group_sales_average_sum'),
            DB::raw('(CASE 
                WHEN SUM(CASE WHEN is_scarce = 0 THEN sales_average ELSE 0 END) OVER (PARTITION BY group_id) > 0 
                THEN sales_average / SUM(CASE WHEN is_scarce = 0 THEN sales_average ELSE 0 END) OVER (PARTITION BY group_id) 
                ELSE 0 
                END) * 100 AS preferencia_product'),
            // cost min solo tiene encuenta los lotes que su quantity sean mayor a 0
            DB::raw('(
                SELECT COALESCE(MIN(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_min'),
            //  cost max solo tiene encuenta los lotes que su quantity sean mayor a 0
            DB::raw('(
                SELECT COALESCE(MAX(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_max'),
            DB::raw('(
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            ) AS totalQuantityInAutoOrder'),
        ];

        // calcular promedio en base a los dias => promedio_calculado
        $lapso = $filtros["lapso_de_tiempo"] ?? "1 month";
        if ($lapso == "7 days") {
            $columnas[] = DB::raw('sales_average / 4 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 4';
        } elseif ($lapso == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        } elseif ($lapso == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        } elseif ($lapso == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        } elseif ($lapso == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        } elseif ($lapso == "12 month" || $lapso == "1 year") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        } elseif ($lapso == "18 month") {
            $columnas[] = DB::raw('sales_average * 18 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 18';
        } elseif ($lapso == "24 month") {
            $columnas[] = DB::raw('sales_average * 24 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 24';
        } else {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        // Subconsulta del AO (unidades ya en pedido activo)
        $subqueryAO = '(SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL)';

        // calcular solicitar: demanda - stock - AO
        $calcSolicitar = '((' . $promedio_calculado . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ')';
        $columnas[] = DB::raw('CASE 
            WHEN ' . $calcSolicitar . ' > 0 THEN CEIL(' . $calcSolicitar . ')
            ELSE FLOOR(' . $calcSolicitar . ')
        END AS solicitar');


        $consulta = Product::select($columnas);
        $consulta->where('is_deleted', false)->where('is_scarce', false)
            ->where(function ($q) {
                $q->whereNull('products.group_id')
                  ->orWhere('products.is_unified_group', '=', 1)
                  ->orWhereNotExists(function ($sq) {
                      $sq->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('products as u')
                          ->whereColumn('u.group_id', 'products.group_id')
                          ->where('u.is_unified_group', '=', 1)
                          ->where('u.is_deleted', false)
                          ->where('u.is_scarce', false);
                  });
            })
            ->with([
            "laboratory",
            "lots",
            "group",
            "productSuppliers" => function ($query) {
                $query->select(
                    'id',
                    'product_id',
                    'supplier_id',
                    'laboratory',
                    'unit_cost_usd',
                    'unit_cost_usd_with_discount'
                )
                    ->with([
                        'supplier' => function ($q) {
                            $q->select('id', 'name');
                        }
                    ])
                    ->orderBy('unit_cost_usd', 'asc');
            }
        ]);


        if (empty($filtros["ids_in"]) && array_key_exists("is_colombia", $filtros)) {
            if ($filtros["is_colombia"] == true) {
                $consulta->where("is_colombian_origin", "=", 1);
            } else if ($filtros["is_colombia"] == false) {
                $consulta->where("is_colombian_origin", "=", 0);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("is_ordered", $filtros)) {
            $consulta->where("is_ordered", "=", $filtros["is_ordered"]);
        }

        if (empty($filtros["ids_in"]) && array_key_exists("product", $filtros)) {
            if (count($filtros["product"]) > 0) {
                $consulta->whereIn("id", $filtros["product"]);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("laboratoryId", $filtros) && !empty($filtros["laboratoryId"])) {
            $consulta->whereIn("laboratory_id", $filtros["laboratoryId"]);
        }

        if (array_key_exists("tipo_vista", $filtros) && $filtros["tipo_vista"] == true) {
            $consulta->join("groups_products", "products.group_id", "=", "groups_products.id")
                ->orderBy("groups_products.name", "ASC");
        } elseif (empty($filtros["ids_in"]) && array_key_exists("groups", $filtros) && !empty($filtros["groups"])) {
            $consulta->whereIn("group_id", $filtros["groups"]);
        }

        if (empty($filtros["ids_in"]) && array_key_exists("q", $filtros) && $filtros["q"] != "") {
            $isStrictSearch = $filtros["isStrictSearch"] ?? false;
            $searchTerm = $filtros["q"];

            $consulta->where(function ($query) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $query->where("name", "like", "%" . $searchTerm . "%")
                        ->orWhere("active_ingredient", "like", "%" . $searchTerm . "%")
                        ->orWhere("barcode", "like", $searchTerm)
                        ->orWhere("id", "like", "%" . $searchTerm . "%");
                } else {
                    $words = explode(' ', trim($searchTerm));
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (empty($word)) continue;
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where("name", "like", "%" . $word . "%")
                                ->orWhere("active_ingredient", "like", "%" . $word . "%")
                                ->orWhere("id", "like", "%" . $word . "%")
                                ->orWhereHas("laboratory", function ($labQuery) use ($word) {
                                    $labQuery->where("name", "like", "%" . $word . "%");
                                });
                        });
                    }
                }
            });
        }

        if (array_key_exists("ids_in", $filtros) && !empty($filtros["ids_in"])) {
            $consulta->whereIn("id", $filtros["ids_in"]);
        }

        if (empty($filtros["ids_in"]) && array_key_exists("without_supplier", $filtros) && $filtros["without_supplier"]) {
            $consulta->doesntHave("productSuppliers");
        }

        // Si ya establecimos ids_in, omitimos este cálculo para no chocar con las fórmulas del Asistente
        if (array_key_exists("stock", $filtros) && empty($filtros["ids_in"])) {
            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", "<", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", ">", 0);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
            });
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $sortCol = $filtros["sortBy"];
            $sortDir = strtolower($filtros["orderBy"]) === 'desc' ? 'desc' : 'asc';
            
            if ($sortCol === 'lote_quantity' || $sortCol === 'stock') {
                $consulta->orderByRaw("(" . $this->subConsultaParaCalcularStockPorLotes . ") $sortDir");
            } elseif ($sortCol === 'total_sold_completed') {
                $consulta->orderByRaw("(
                    SELECT COALESCE(SUM(order_details.quantity), 0)
                    FROM order_details
                    JOIN orders ON orders.id = order_details.order_id
                    WHERE order_details.product_id = products.id
                    AND orders.created_at BETWEEN '{$filtros["previousDate"]}' AND '{$filtros["dateToday"]}'
                    AND orders.status = 'Completed'
                ) $sortDir");
            } elseif ($sortCol === 'promedio_calculado') {
                $consulta->orderByRaw("($promedio_calculado) $sortDir");
            } elseif ($sortCol === 'totalQuantityInAutoOrder') {
                $consulta->orderByRaw("($subqueryAO) $sortDir");
            } elseif ($sortCol === 'solicitar') {
                $consulta->orderByRaw("(CASE 
                    WHEN $calcSolicitar > 0 THEN CEIL($calcSolicitar) 
                    ELSE FLOOR($calcSolicitar) 
                END) $sortDir");
            } elseif ($sortCol === 'best_supplier_percentage') {
                $subqueryBestSupplierPrice = '(
                    SELECT MIN(
                        CASE 
                            WHEN ps.unit_cost_usd_with_discount > 0 THEN ps.unit_cost_usd_with_discount 
                            ELSE ps.unit_cost 
                        END
                    )
                    FROM product_suppliers ps
                    WHERE ps.product_id = products.id
                )';
                $subqueryVariation = "CASE 
                    WHEN products.unit_cost > 0 THEN 
                        ((($subqueryBestSupplierPrice) - products.unit_cost) / products.unit_cost) * 100
                    ELSE 0
                END";
                $dbSortDir = $sortDir === 'desc' ? 'asc' : 'desc';
                $consulta->whereRaw("($subqueryBestSupplierPrice) > 0");
                $consulta->orderByRaw("($subqueryVariation) $dbSortDir");
            } else {
                $consulta->orderBy($sortCol, $sortDir);
            }
        } else {
            $consulta->orderBy("name", "ASC");
        }


        return $consulta;
    }

    public function builerFiltrarIndividualProductForAssistantReportTypeSales($filtros): Builder
    {
        // solicitar = stock - ventas individuales
        $ventasIndividualDelProducto = '
            (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            )

        ';


        $columnas = [
            'products.id',
            'products.name',
            DB::raw('(' . $this->subConsultaParaCalcularStockPorLotes . ') as stock'),
            'products.group_id',
            'products.laboratory_id',
            'products.barcode',
            "products.is_ordered",
            "products.sales_average",
            "products.sale_price",
            "products.unit_cost",
            "products.psychotropic",
            "products.is_colombian_origin",
            "products.is_unified_group",
            "products.active_ingredient",
            'products.manual_solicitar',
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(' . $this->subConsultaParaCalcularStockPorLotes . ') AS lote_quantity'),
            DB::raw('(
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            ) AS total_sold_completed'),
            DB::raw('(
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = "Completed"
                AND p.is_scarce = 0 AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) AS total_group_sales'),
            //  para calcular la preferencia del producto veta del producto / ventas totales del grupo que pertenece el producto * 100
            DB::raw(' NULLIF((
            (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            ) / (
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = "Completed"
                AND p.is_scarce = 0 AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) 
            ),0)* 100 AS preferencia_product'),
            DB::raw('(
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            ) AS totalQuantityInAutoOrder'),
            DB::raw('CASE 
                WHEN ((' . $ventasIndividualDelProducto . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                    SELECT COALESCE(SUM(aod.quantity), 0)
                    FROM auto_order_details aod
                    JOIN auto_orders ao ON ao.id = aod.order_id
                    JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                    WHERE ps.product_id = products.id
                    AND ao.status IN (0, 1)
                    AND aod.status = 0
                    AND ao.deleted_at IS NULL
                    AND aod.deleted_at IS NULL
                )) > 0 THEN CEIL((' . $ventasIndividualDelProducto . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                    SELECT COALESCE(SUM(aod.quantity), 0)
                    FROM auto_order_details aod
                    JOIN auto_orders ao ON ao.id = aod.order_id
                    JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                    WHERE ps.product_id = products.id
                    AND ao.status IN (0, 1)
                    AND aod.status = 0
                    AND ao.deleted_at IS NULL
                    AND aod.deleted_at IS NULL
                ))
                ELSE FLOOR((' . $ventasIndividualDelProducto . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - (
                    SELECT COALESCE(SUM(aod.quantity), 0)
                    FROM auto_order_details aod
                    JOIN auto_orders ao ON ao.id = aod.order_id
                    JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                    WHERE ps.product_id = products.id
                    AND ao.status IN (0, 1)
                    AND aod.status = 0
                    AND ao.deleted_at IS NULL
                    AND aod.deleted_at IS NULL
                ))
            END AS solicitar'),
            // cost min solo tiene encuenta los lotes que su quantity sean mayor a 0
            DB::raw('(
                SELECT COALESCE(MIN(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_min'),
            //  cost max solo tiene encuenta los lotes que su quantity sean mayor a 0
            DB::raw('(
                SELECT COALESCE(MAX(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_max'),
        ];

        // calcular promedio en base a los dias => promedio_calculado
        $lapso = $filtros["lapso_de_tiempo"] ?? "1 month";
        if ($lapso == "7 days") {
            $columnas[] = DB::raw('sales_average / 4 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 4';
        } elseif ($lapso == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        } elseif ($lapso == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        } elseif ($lapso == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        } elseif ($lapso == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        } elseif ($lapso == "1 year") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        } else {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        $consulta = Product::select($columnas);
        $consulta->where('is_deleted', false)->where('is_scarce', false)
            ->where(function ($q) {
                $q->whereNull('products.group_id')
                  ->orWhere('products.is_unified_group', '=', 1)
                  ->orWhereNotExists(function ($sq) {
                      $sq->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('products as u')
                          ->whereColumn('u.group_id', 'products.group_id')
                          ->where('u.is_unified_group', '=', 1)
                          ->where('u.is_deleted', false)
                          ->where('u.is_scarce', false);
                  });
            })
            ->with([
            "laboratory",
            "lots",
            "group",
            "productSuppliers" => function ($query) {
                $query->select(
                    'id',
                    'product_id',
                    'supplier_id',
                    'laboratory',
                    'unit_cost_usd',
                    'unit_cost_usd_with_discount'
                )
                    ->with([
                        'supplier' => function ($q) {
                            $q->select('id', 'name');
                        }
                    ])
                    ->orderBy('unit_cost_usd', 'asc');
            }
        ]);

        if (array_key_exists("id", $filtros) && !empty($filtros["id"])) {
            $consulta->where("id", $filtros["id"]);
        }

        if (empty($filtros["ids_in"]) && array_key_exists("is_colombia", $filtros)) {
            if ($filtros["is_colombia"] == true) {
                $consulta->where("is_colombian_origin", "=", 1);
            } else if ($filtros["is_colombia"] == false) {
                $consulta->where("is_colombian_origin", "=", 0);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("is_ordered", $filtros)) {
            $consulta->where("is_ordered", "=", $filtros["is_ordered"]);
        }

        if (empty($filtros["ids_in"]) && array_key_exists("product", $filtros)) {
            if (count($filtros["product"]) > 0) {
                $consulta->whereIn("id", $filtros["product"]);
            }
        }

        if (array_key_exists("ids_in", $filtros)) {
            if (count($filtros["ids_in"]) > 0) {
                $consulta->whereIn("id", $filtros["ids_in"]);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("laboratoryId", $filtros)) {
            if (count($filtros["laboratoryId"]) > 0) {
                $consulta->whereIn("laboratory_id", $filtros["laboratoryId"]);
            }
        }

        if (array_key_exists("tipo_vista", $filtros) && $filtros["tipo_vista"] == true) {
            $consulta->join("groups_products", "products.group_id", "=", "groups_products.id")
                ->orderBy("groups_products.name", "ASC");
        } elseif (empty($filtros["ids_in"]) && array_key_exists("groups", $filtros)) {
            if (count($filtros["groups"]) > 0) {
                $consulta->whereIn("products.group_id", $filtros["groups"]);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("q", $filtros) && $filtros["q"] != "") {
            $isStrictSearch = $filtros["isStrictSearch"] ?? false;
            $searchTerm = $filtros["q"];

            $consulta->where(function ($query) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $query->where("name", "like", "%" . $searchTerm . "%")
                        ->orWhere("active_ingredient", "like", "%" . $searchTerm . "%")
                        ->orWhere("barcode", "like", $searchTerm)
                        ->orWhere("id", "like", "%" . $searchTerm . "%");
                } else {
                    $words = explode(' ', trim($searchTerm));
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (empty($word)) continue;
                        $query->where(function ($wordQuery) use ($word) {
                            $wordQuery->where("name", "like", "%" . $word . "%")
                                ->orWhere("active_ingredient", "like", "%" . $word . "%")
                                ->orWhere("id", "like", "%" . $word . "%")
                                ->orWhereHas("laboratory", function ($labQuery) use ($word) {
                                    $labQuery->where("name", "like", "%" . $word . "%");
                                });
                        });
                    }
                }
            });
        }

        if (empty($filtros["ids_in"]) && array_key_exists("without_supplier", $filtros) && $filtros["without_supplier"]) {
            $consulta->doesntHave("productSuppliers");
        }

        // Si ya establecimos ids_in, omitimos este cálculo para no chocar con las fórmulas del Asistente
        if (array_key_exists("stock", $filtros) && empty($filtros["ids_in"])) {
            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", "<", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", ">", 0);
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
            });
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $sortCol = $filtros["sortBy"];
            $sortDir = strtolower($filtros["orderBy"]) === 'desc' ? 'desc' : 'asc';
            
            $subqueryAO = '(
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            )';
            
            if ($sortCol === 'lote_quantity' || $sortCol === 'stock') {
                $consulta->orderByRaw("(" . $this->subConsultaParaCalcularStockPorLotes . ") $sortDir");
            } elseif ($sortCol === 'total_sold_completed') {
                $consulta->orderByRaw("($ventasIndividualDelProducto) $sortDir");
            } elseif ($sortCol === 'promedio_calculado') {
                $consulta->orderByRaw("($promedio_calculado) $sortDir");
            } elseif ($sortCol === 'totalQuantityInAutoOrder') {
                $consulta->orderByRaw("($subqueryAO) $sortDir");
            } elseif ($sortCol === 'solicitar') {
                $calcSolicitarSales = "(($ventasIndividualDelProducto) - {$this->subConsultaParaCalcularStockPorLotes} - $subqueryAO)";
                $consulta->orderByRaw("(CASE 
                    WHEN $calcSolicitarSales > 0 THEN CEIL($calcSolicitarSales) 
                    ELSE FLOOR($calcSolicitarSales) 
                END) $sortDir");
            } elseif ($sortCol === 'best_supplier_percentage') {
                $subqueryBestSupplierPrice = '(
                    SELECT MIN(
                        CASE 
                            WHEN ps.unit_cost_usd_with_discount > 0 THEN ps.unit_cost_usd_with_discount 
                            ELSE ps.unit_cost 
                        END
                    )
                    FROM product_suppliers ps
                    WHERE ps.product_id = products.id
                )';
                $subqueryVariation = "CASE 
                    WHEN products.unit_cost > 0 THEN 
                        ((($subqueryBestSupplierPrice) - products.unit_cost) / products.unit_cost) * 100
                    ELSE 0
                END";
                $dbSortDir = $sortDir === 'desc' ? 'asc' : 'desc';
                $consulta->whereRaw("($subqueryBestSupplierPrice) > 0");
                $consulta->orderByRaw("($subqueryVariation) $dbSortDir");
            } else {
                $consulta->orderBy($sortCol, $sortDir);
            }
        } else {
            $consulta->orderBy("solicitar", "DESC");
        }


        return $consulta;
    }

    public function filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);

        return $consulta->get();
    }

    public function filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);

        return $consulta->paginate($perPage);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);

        return $consulta->get();
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);

        return $consulta->paginate($perPage);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesToArray($filtros): array
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);

        return $consulta->get()->toArray();
    }

    public function getUniqueIdsForIaReport($filtros, $porGrupo = false): array
    {
        // 1. Definir subconsultas y variables base
        $subqueryStock = 'CASE 
            WHEN products.is_unified_group = 1 AND products.group_id IS NOT NULL THEN (
                SELECT COALESCE(SUM(quantity), 0) 
                FROM product_lots 
                JOIN products as u_p ON u_p.id = product_lots.product_id
                WHERE u_p.group_id = products.group_id 
                AND u_p.is_deleted = 0 
                AND u_p.is_scarce = 0
            )
            ELSE (
                SELECT COALESCE (SUM(quantity), 0) 
                FROM product_lots 
                WHERE product_id = products.id 
            )
        END';

        $subqueryAO = 'CASE 
            WHEN products.is_unified_group = 1 AND products.group_id IS NOT NULL THEN (
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                JOIN products as u_p ON u_p.id = ps.product_id
                WHERE u_p.group_id = products.group_id
                AND u_p.is_deleted = 0 
                AND u_p.is_scarce = 0
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            )
            ELSE (
                SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL
            )
        END';

        $subqueryTotalSold = 'CASE 
            WHEN products.is_unified_group = 1 AND products.group_id IS NOT NULL THEN (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                JOIN products as u_p ON u_p.id = order_details.product_id
                WHERE u_p.group_id = products.group_id
                AND u_p.is_deleted = 0 
                AND u_p.is_scarce = 0
                AND orders.created_at BETWEEN \'' . ($filtros["previousDate"] ?? date('Y-m-d', strtotime('-30 days'))) . '\' AND \'' . ($filtros["dateToday"] ?? date('Y-m-d H:i:s')) . '\'
                AND orders.status = "Completed"
            )
            ELSE (
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . ($filtros["previousDate"] ?? date('Y-m-d', strtotime('-30 days'))) . '\' AND \'' . ($filtros["dateToday"] ?? date('Y-m-d H:i:s')) . '\'
                AND orders.status = "Completed"
            )
        END';

        // Promedio calculado según lapso
        $lapso = $filtros["lapso_de_tiempo"] ?? "1 month";
        $sumSalesAverage = 'CASE 
            WHEN products.is_unified_group = 1 AND products.group_id IS NOT NULL THEN (
                SELECT COALESCE(SUM(sales_average), 0)
                FROM products as u_p
                WHERE u_p.group_id = products.group_id
                AND u_p.is_deleted = 0 
                AND u_p.is_scarce = 0
            )
            ELSE products.sales_average
        END';

        $promedio_calculado = match($lapso) {
            "7 days"  => "($sumSalesAverage) / 4",
            "15 days" => "($sumSalesAverage) / 2",
            "1 month" => "($sumSalesAverage)",
            "3 month" => "($sumSalesAverage) * 3",
            "6 month" => "($sumSalesAverage) * 6",
            "1 year"  => "($sumSalesAverage) * 12",
            default    => "($sumSalesAverage)",
        };

        // Solicitar según tipo de filtración
        $tipo = $filtros["tipo_filtracion"] ?? "average";
        if ($tipo === "combinado") {
            // Demanda ponderada combinada: Si hay ventas usamos (ventas + promedio)/2. 
            // Si subqueryTotalSold es 0, usamos el promedio directamente (igual que PHP).
            $demanda = '((' . $promedio_calculado . ' + ' . $subqueryTotalSold . ') / 2)';
            $solicitarRaw = '(' . $demanda . ' - ' . $subqueryStock . ' - ' . $subqueryAO . ')';
        } elseif ($tipo === "sales") {
            $solicitarRaw = '(' . $subqueryTotalSold . ' - ' . $subqueryStock . ' - ' . $subqueryAO . ')';
        } else {
            $solicitarRaw = '(' . $promedio_calculado . ' - ' . $subqueryStock . ' - ' . $subqueryAO . ')';
        }

        $solicitarCol = "CASE 
                WHEN ($solicitarRaw) > 0 THEN CEIL($solicitarRaw) 
                ELSE FLOOR($solicitarRaw) 
            END";

        // 2. Construir Query
        $query = Product::query()
            ->where('is_deleted', false)
            ->where('is_scarce', false)
            ->where(function ($q) {
                $q->whereNull('products.group_id')
                  ->orWhere('products.is_unified_group', '=', 1)
                  ->orWhereNotExists(function ($sq) {
                      $sq->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('products as u')
                          ->whereColumn('u.group_id', 'products.group_id')
                          ->where('u.is_unified_group', '=', 1)
                          ->where('u.is_deleted', false)
                          ->where('u.is_scarce', false);
                  });
            })
            ->when(!($filtros['show_ignored'] ?? false), function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('ignore_until')
                       ->orWhere('ignore_until', '<=', now());
                });
            });

        // Aplicar filtros directos de producto en la cláusula WHERE (Reducen drásticamente el universo de datos)
        if (!empty($filtros["ids_in"])) {
            $query->whereIn("products.id", $filtros["ids_in"]);
        }
        if (empty($filtros["ids_in"]) && array_key_exists("laboratoryId", $filtros) && !empty($filtros["laboratoryId"])) {
            $query->whereIn("products.laboratory_id", $filtros["laboratoryId"]);
        }
        if (array_key_exists("groups", $filtros) && !empty($filtros["groups"])) {
            $query->whereIn("products.group_id", $filtros["groups"]);
        }
        if (array_key_exists("isColombian", $filtros)) {
            if ($filtros["isColombian"] == true || $filtros["isColombian"] === "true") {
                $query->where("products.is_colombian_origin", "=", 1);
            } elseif ($filtros["isColombian"] === false || $filtros["isColombian"] === "false") {
                $query->where(function ($q) {
                    $q->where("products.is_colombian_origin", "=", 0)
                      ->orWhereNull("products.is_colombian_origin");
                });
            }
        }

        if (array_key_exists("isNovaventa", $filtros)) {
            if ($filtros["isNovaventa"] == true || $filtros["isNovaventa"] === "true") {
                $query->where("products.is_novaventa", "=", 1);
            } elseif ($filtros["isNovaventa"] === false || $filtros["isNovaventa"] === "false") {
                $query->where(function ($q) {
                    $q->where("products.is_novaventa", "=", 0)
                      ->orWhereNull("products.is_novaventa");
                });
            }
        }

        if (array_key_exists("q", $filtros) && $filtros["q"] != "") {
             $query->where(function($q) use ($filtros) {
                 $q->where("products.name", "like", "%" . $filtros["q"] . "%")
                   ->orWhere("products.id", "like", "%" . $filtros["q"] . "%");
             });
        }

        // Seleccionar solicitar para usar en el teniendo (HAVING) sólo sobre los productos ya filtrados
        $query->select('products.id', 'products.group_id', 'products.name', \Illuminate\Support\Facades\DB::raw("$solicitarCol AS solicitar"));

        if (array_key_exists("hasStock", $filtros)) {
            $hasStockVal = $filtros["hasStock"];
            if ($hasStockVal === true || $hasStockVal === 'true' || $hasStockVal === 1) {
                $query->whereRaw("($subqueryStock) > 0");
            } elseif ($hasStockVal === false || $hasStockVal === 'false' || $hasStockVal === 0) {
                $query->whereRaw("($subqueryStock) = 0");
            }
        }
        if (array_key_exists("q", $filtros) && $filtros["q"] != "") {
             $query->where(function($q) use ($filtros) {
                 $q->where("products.name", "like", "%" . $filtros["q"] . "%")
                   ->orWhere("products.id", "like", "%" . $filtros["q"] . "%");
             });
        }

        // 3. Aplicar Filtro de Stock (HAVING)
        if (array_key_exists("stock", $filtros) && $filtros["stock"] !== 'all') {
            if ($filtros["stock"] == "exceso") {
                $query->having("solicitar", "<", 0);
            } elseif ($filtros["stock"] == "fallas") {
                $query->having("solicitar", ">", 0);
            }
        }

        // 4. Ordenamiento Dinámico
        $sortCol = !empty($filtros['sortBy']) ? $filtros['sortBy'] : 'products.name';
        $sortDir = strtolower(!empty($filtros['orderBy']) ? $filtros['orderBy'] : 'asc') === 'desc' ? 'desc' : 'asc';

        // Mapeo de columnas de ordenamiento para tablas
        if ($sortCol === 'lote_quantity' || $sortCol === 'stock') {
            $query->orderByRaw("($subqueryStock) $sortDir");
        } elseif ($sortCol === 'total_sold_completed') {
            $query->orderByRaw("($subqueryTotalSold) $sortDir");
        } elseif ($sortCol === 'promedio_calculado') {
            $query->orderByRaw("($promedio_calculado) $sortDir");
        } elseif ($sortCol === 'totalQuantityInAutoOrder') {
            $query->orderByRaw("($subqueryAO) $sortDir");
        } elseif ($sortCol === 'solicitar') {
            $query->orderByRaw("($solicitarCol) $sortDir");
        } elseif ($sortCol === 'best_supplier_percentage') {
            $subqueryBestSupplierPrice = '(
                SELECT MIN(
                    CASE 
                        WHEN ps.unit_cost_usd_with_discount > 0 THEN ps.unit_cost_usd_with_discount 
                        ELSE ps.unit_cost 
                    END
                )
                FROM product_suppliers ps
                WHERE ps.product_id = products.id
            )';
            $subqueryVariation = "CASE 
                WHEN products.unit_cost > 0 THEN 
                    ((($subqueryBestSupplierPrice) - products.unit_cost) / products.unit_cost) * 100
                ELSE 0
            END";
            $dbSortDir = $sortDir === 'desc' ? 'asc' : 'desc';
            $query->whereRaw("($subqueryBestSupplierPrice) > 0");
            $query->orderByRaw("($subqueryVariation) $dbSortDir");
        } elseif ($sortCol === 'preferencia_product') {
            // Como preferencia requiere group_sales_average_sum, lo manejamos con una subconsulta simplificada o por products.name si falla
            $query->orderBy('products.name', 'ASC');
        } else {
            $query->orderBy($sortCol, $sortDir);
        }

        if ($porGrupo) {
            return $query
                ->join('groups_products', 'products.group_id', '=', 'groups_products.id')
                ->addSelect('groups_products.name')
                // No aplicar el orden dinámico aquí si es por grupo, habitualmente es alfabético
                ->orderBy('groups_products.name', 'ASC')
                ->get()
                ->pluck('group_id')
                ->unique()
                ->values()
                ->toArray();
        }

        return $query->get()
            ->pluck('id')
            ->toArray();
    }
}
