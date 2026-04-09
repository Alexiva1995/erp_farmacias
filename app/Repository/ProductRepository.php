<?php
// TODO: identificar las consulta de auto orden compo consulta por grupo

namespace App\Repository;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository
{

    private $subConsultaParaCalcularStockPorLotes = '(SELECT COALESCE (SUM(quantity), 0) 
                FROM product_lots 
                WHERE product_id = products.id 
                AND (expiration_date >= CURDATE() OR expiration_date IS NULL))';

    public function consultProductById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function consultarTodosLosProductOrdenaPor($sortBy = "name", $orderBy = "ASC")
    {
        return Product::query()->orderBy($sortBy, $orderBy)->get();
    }

    public function builerFiltrarProductforStock($filtros): Builder
    {
        $viewType = $filtros["viewType"] ?? "individual";
        $isGroup = $viewType === "group";

        // Asegurar que previousDate y dateToday estén definidos para cálculos de ventas y combinado
        $days = $filtros["days"] ?? 30;
        $timeZone = new \DateTimeZone(config("app.timezone"));
        $dateToday = new \DateTime("now", $timeZone);
        $previousDate = new \DateTime("now", $timeZone);
        $previousDate->modify("-" . $days . " days");
        
        $dateTodayStr = $filtros["dateToday"] ?? $dateToday->format("Y-m-d H:i:s");
        $previousDateStr = $filtros["previousDate"] ?? $previousDate->format("Y-m-d");

        // Subconsultas Base
        $subqueryStockLotes = $this->subConsultaParaCalcularStockPorLotes;
        $subqueryTotalSold = '(SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $previousDateStr . '\' AND \'' . $dateTodayStr . '\'
                AND orders.status = "Completed")';

        $subqueryAO = '(SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL)';

        // Cálculo de Promedio
        $lapso = $filtros["lapso_de_tiempo"] ?? $filtros["days"] ?? 30;
        if (!is_string($lapso)) $lapso = $lapso . " days";

        if (str_contains($lapso, "7")) {
            $promedioCalculadoSql = 'products.sales_average / 4';
        } elseif (str_contains($lapso, "15")) {
            $promedioCalculadoSql = 'products.sales_average / 2';
        } elseif (str_contains($lapso, "60")) {
            $promedioCalculadoSql = 'products.sales_average * 2';
        } elseif (str_contains($lapso, "90")) {
            $promedioCalculadoSql = 'products.sales_average * 3';
        } else {
            $promedioCalculadoSql = 'products.sales_average';
        }

        // Definición de Columnas
        if ($isGroup) {
            $columnas = [
                DB::raw('MIN(products.id) as id'),
                DB::raw('COALESCE(groups_products.name, CONCAT("INDIVIDUAL: ", products.name)) as name'),
                DB::raw('products.group_id'),
                DB::raw('MAX(products.photo_url) as photo_url'),
                DB::raw('SUM(' . $subqueryStockLotes . ') as lote_quantity'),
                DB::raw('SUM(' . $subqueryTotalSold . ') as total_sold_completed'),
                DB::raw('SUM(' . $subqueryTotalSold . ') as preferencia_product'),
                DB::raw('SUM(' . $promedioCalculadoSql . ') as promedio_calculado'),
                DB::raw('SUM(' . $subqueryAO . ') as totalQuantityInAutoOrder'),
                DB::raw('MAX(products.unit_cost) as unit_cost'),
                DB::raw('MAX(products.laboratory_id) as laboratory_id'),
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
                DB::raw($subqueryStockLotes . ' AS lote_quantity'),
                DB::raw($subqueryTotalSold . ' AS total_sold_completed'),
                DB::raw($subqueryTotalSold . ' AS preferencia_product'),
                DB::raw($promedioCalculadoSql . ' AS promedio_calculado'),
                DB::raw($subqueryAO . ' AS totalQuantityInAutoOrder'),
            ];
        }

        // Diferencia de Producto
        $tipoFiltracion = $filtros["tipo_filtracion"] ?? "average";
        $stockEfectivo = '(COALESCE((' . $subqueryStockLotes . '), 0) + COALESCE((' . $subqueryAO . '), 0))';
        
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
        $consulta = Product::select($columnas);

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
            if ($filtros["isColombian"] == true) {
                $consulta->where("products.is_colombian_origin", "=", 1);
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $sortCol = $filtros["sortBy"];
            $sortDir = strtolower($filtros["orderBy"]) === 'desc' ? 'desc' : 'asc';
            
            // Mapear columnas si es necesario (ej: stock -> lote_quantity)
            if ($sortCol === 'stock') $sortCol = 'lote_quantity';
            
            $consulta->orderBy($sortCol, $sortDir);
        } else {
            $consulta->orderBy("diferencia_product", "DESC");
        }

        return $consulta;
    }


    public function filtrarProductforStocktWithoutPaginate($filtros): Collection
    {

        $consulta = $this->builerFiltrarProductforStock($filtros);

        return $consulta->get();
    }



    public function filtrarProductforStocktWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {

        $consulta = $this->builerFiltrarProductforStock($filtros);

        return $consulta->paginate($perPage);
    }

    /**
     * esta consulta calcula la preferencia_product por promedio del grupo al que pertence el porducto 
     */
    public function builerFiltrarProductForIaOrderAssistantTypeAverage($filtros): Builder
    {
        // solicitar = stock - promedio

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
            DB::raw('(
                SELECT ps.unit_cost_usd
                FROM product_suppliers ps
                WHERE ps.product_id = products.id
                  AND ps.barcode_match IS NOT NULL
                  AND ps.barcode_match != \'\'
                  AND ps.unit_cost_usd > 0
                ORDER BY ps.unit_cost_usd ASC
                LIMIT 1
            ) AS cheapest_unit_cost'),
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

        // calcular solicitar
        // Subconsulta para total_sold_completed (reutilizable)
        $subqueryTotalSold = '(
                SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
                AND orders.status = "Completed"
            )';

        // Sub-consulta del AO (unidades ya en pedido activo) para incluirla en el cálculo SQL
        $subqueryAO = '(SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL)';

        // calcular solicitar: demanda - stock - AO  (positivo = falta, negativo = exceso)
        // Redondear hacia arriba si falta, hacia abajo si sobra (CEIL/FLOOR)
        $tipo_filtracion = $filtros["tipo_filtracion"] ?? "average";
        if ($tipo_filtracion == "combinado") {
            // Demanda combinada = (promedio + ventas) / 2
            $demandaCombinada = '((' . $promedio_calculado . ' + ' . $subqueryTotalSold . ') / 2)';
            // solicitar = demanda - stock - AO
            $columnas[] = DB::raw('CASE 
                WHEN ((' . $demandaCombinada . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ') > 0 THEN CEIL((' . $demandaCombinada . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ')
                ELSE FLOOR((' . $demandaCombinada . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ')
            END AS solicitar');
            // demanda_ponderada = (promedio + ventas) / 2 (antes de restar stock)
            $columnas[] = DB::raw('((' . $promedio_calculado . ' + ' . $subqueryTotalSold . ') / 2) AS demanda_ponderada');
        } else {
            // solicitar = promedio - stock - AO
            $columnas[] = DB::raw('CASE 
                WHEN ((' . $promedio_calculado . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ') > 0 THEN CEIL((' . $promedio_calculado . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ')
                ELSE FLOOR((' . $promedio_calculado . ') - ' . $this->subConsultaParaCalcularStockPorLotes . ' - ' . $subqueryAO . ')
            END AS solicitar');
            // demanda_ponderada = (promedio + ventas) / 2 (antes de restar stock)
            $columnas[] = DB::raw('((' . $promedio_calculado . ' + ' . $subqueryTotalSold . ') / 2) AS demanda_ponderada');
        }

        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"])->where('is_deleted', false)->where('is_scarce', false);

        if (array_key_exists("ids", $filtros)) {
            $consulta->whereIn("id", $filtros["ids"]);
        }


        // if (array_key_exists("sin_proveedor", $filtros)) {
        //     $consulta->doesntHave("productSuppliers");
        // }

        if (array_key_exists("ids_in", $filtros) && !empty($filtros["ids_in"])) {
            $consulta->whereIn("products.id", $filtros["ids_in"]);
        }

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

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->having("lote_quantity", ">", 0);
            } else {
                $consulta->having("lote_quantity", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                // Con la nueva fórmula: demanda - stock - AO < 0 = exceso de cobertura
                $consulta->having("solicitar", "<", 0);
            }
            if ($filtros["stock"] == "fallas") {
                // Ahora unificado: demanda - stock - AO > 0 = falla (puramente matemático)
                // Y excepcion: solicitar es 0, y el stock físico también es 0
                $consulta->havingRaw("solicitar > 0 OR (solicitar = 0 AND (" . $this->subConsultaParaCalcularStockPorLotes . ") <= 0)");
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

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("solicitar", "DESC");
        }


        return $consulta;
    }

    public function filtrarProductforIaOrderAssistantTypeAverageWithoutPaginate($filtros): Collection
    {

        $consulta = $this->builerFiltrarProductForIaOrderAssistantTypeAverage($filtros);

        return $consulta->get();
    }

    public function filtrarProductforIaOrderAssistantTypeAverageWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {

        $consulta = $this->builerFiltrarProductForIaOrderAssistantTypeAverage($filtros);

        return $consulta->paginate($perPage);
    }

    /**
     * esta consulta calcula la preferencia_product por ventas del grupo al que pertence el porducto 
     */
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
            DB::raw('CASE 
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
            END AS solicitar'),
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
            DB::raw('(
                SELECT ps.unit_cost_usd
                FROM product_suppliers ps
                WHERE ps.product_id = products.id
                  AND ps.barcode_match IS NOT NULL
                  AND ps.barcode_match != \'\'
                  AND ps.unit_cost_usd > 0
                ORDER BY ps.unit_cost_usd ASC
                LIMIT 1
            ) AS cheapest_unit_cost'),
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
                // Y excepcion: solicitar es 0, y el stock físico también es 0
                $consulta->havingRaw("solicitar > 0 OR (solicitar = 0 AND (" . $this->subConsultaParaCalcularStockPorLotes . ") <= 0)");
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
                    'unit_cost_usd_with_discount'
                )
                    ->with([
                        'supplier' => function ($q) {
                            $q->select('id', 'name');
                        }
                    ])
                    ->orderBy('unit_cost_usd_with_discount', 'asc');
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
                $consulta->havingRaw("solicitar > 0 OR (solicitar = 0 AND (" . $this->subConsultaParaCalcularStockPorLotes . ") <= 0)");
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
            });
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
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
                    'unit_cost_usd_with_discount'
                )
                    ->with([
                        'supplier' => function ($q) {
                            $q->select('id', 'name');
                        }
                    ])
                    ->orderBy('unit_cost_usd_with_discount', 'asc');
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
                $consulta->havingRaw("solicitar > 0 OR (solicitar = 0 AND (" . $this->subConsultaParaCalcularStockPorLotes . ") <= 0)");
            }
        }

        if (empty($filtros["ids_in"]) && array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
            });
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
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
        $subqueryStock = $this->subConsultaParaCalcularStockPorLotes;
        $subqueryAO = '(SELECT COALESCE(SUM(aod.quantity), 0)
                FROM auto_order_details aod
                JOIN auto_orders ao ON ao.id = aod.order_id
                JOIN product_suppliers ps ON ps.id = aod.product_suppliers_id
                WHERE ps.product_id = products.id
                AND ao.status IN (0, 1)
                AND aod.status = 0
                AND ao.deleted_at IS NULL
                AND aod.deleted_at IS NULL)';

        $subqueryTotalSold = '(SELECT COALESCE(SUM(order_details.quantity), 0)
                FROM order_details
                JOIN orders ON orders.id = order_details.order_id
                WHERE order_details.product_id = products.id
                AND orders.created_at BETWEEN \'' . ($filtros["previousDate"] ?? date('Y-m-d', strtotime('-30 days'))) . '\' AND \'' . ($filtros["dateToday"] ?? date('Y-m-d H:i:s')) . '\'
                AND orders.status = "Completed")';

        // Promedio calculado según lapso
        $lapso = $filtros["lapso_de_tiempo"] ?? "1 month";
        $promedio_calculado = match($lapso) {
            "7 days"  => 'sales_average / 4',
            "15 days" => 'sales_average / 2',
            "1 month" => 'sales_average',
            "3 month" => 'sales_average * 3',
            "6 month" => 'sales_average * 6',
            "1 year"  => 'sales_average * 12',
            default    => 'sales_average',
        };

        // Solicitar según tipo de filtración
        $tipo = $filtros["tipo_filtracion"] ?? "average";
        if ($tipo === "combinado") {
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
                $q->whereNull('ignore_until')
                  ->orWhere('ignore_until', '<=', now());
            })
            ->whereNotExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('auto_order_details')
                    ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
                    ->whereColumn('auto_order_details.product_id', 'products.id')
                    ->where('auto_orders.status', 0) // Open
                    ->whereNull('auto_orders.deleted_at')
                    ->whereNull('auto_order_details.deleted_at');
            });

        // Seleccionar solicitar para usar en having
        $query->select('products.id', 'products.group_id', 'products.name', \Illuminate\Support\Facades\DB::raw("$solicitarCol AS solicitar"));

        // Filtros base
        if (empty($filtros["ids_in"]) && array_key_exists("laboratoryId", $filtros) && !empty($filtros["laboratoryId"])) {
            $query->whereIn("laboratory_id", $filtros["laboratoryId"]);
        }
        if (array_key_exists("groups", $filtros) && !empty($filtros["groups"])) {
            $query->whereIn("group_id", $filtros["groups"]);
        }
        if (array_key_exists("isColombian", $filtros) && $filtros["isColombian"] == true) {
            $query->where("is_colombian_origin", "=", 1);
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
                $query->havingRaw("solicitar > 0 OR (solicitar = 0 AND ($subqueryStock) <= 0)");
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
