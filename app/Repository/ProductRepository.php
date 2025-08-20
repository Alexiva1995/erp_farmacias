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

    public function consultarTodosLosProductOrdenaPor($sortBy = "name", $orderBy = "ASC")
    {
        return Product::query()->orderBy($sortBy, $orderBy)->get();
    }

    public function builerFiltrarProductforStock($filtros): Builder
    {

        $columnas = [
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            "sale_price",
            "unit_cost",
            "psychotropic",
            "is_colombian_origin",
            "active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(SELECT quantity 
                    FROM product_lots 
                    WHERE product_id = products.id
                    AND expiration_date = (
                        SELECT MIN(expiration_date)
                        FROM product_lots
                        WHERE product_id = products.id
                        AND expiration_date >= CURDATE()
                        ) LIMIT 1) AS lote_quantity'),
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
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
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
        ];

        // calcular promedio en vace a los dias => promedio_calculado
        $promedio_calculado = "";
        if ($filtros["days"] == 15) {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        }

        if ($filtros["days"] == 30) {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        if ($filtros["days"] == 60) {
            $columnas[] = DB::raw('sales_average * 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 2';
        }

        if ($filtros["days"] == 90) {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        }

        // calcular diferencia_product con promedio_calculado
        $columnas[] = DB::raw('stock - (' . $promedio_calculado . ') AS diferencia_product');

        // calcular demanda_ajustada con promedio_calculado
        $columnas[] =  DB::raw('COALESCE(
                (SELECT quantity FROM product_lots 
                WHERE product_id = products.id
                AND expiration_date = (
                    SELECT MIN(expiration_date)
                    FROM product_lots
                    WHERE product_id = products.id
                    AND expiration_date >= CURDATE()
                ) LIMIT 1), 0) - 
                ((' . $promedio_calculado . ') * 
                COALESCE((SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date))
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND expiration_date >= CURDATE()), 0)
            ) AS demanda_ajustada');


        $consulta = Product::select($columnas)->with(["laboratory", "lots"]);

        if (array_key_exists("q", $filtros)) {
            if ($filtros["q"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["q"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["q"] . "%");
                });
            }
        }


        if (array_key_exists("laboratoryId", $filtros)) {
            $consulta->where("laboratory_id", "=", $filtros["laboratoryId"]);
        }

        if (array_key_exists("expProd", $filtros)) {
            if ($filtros["expProd"] == true) {
                $consulta->having("demanda_ajustada", ">", 0);
            }
        }

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->where("stock", ">", 0);
            } else {
                $consulta->where("stock", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("diferencia_product", ">", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("diferencia_product", "<", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
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
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            "sale_price",
            "unit_cost",
            "psychotropic",
            "is_colombian_origin",
            "active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(SELECT quantity 
                    FROM product_lots 
                    WHERE product_id = products.id
                    AND expiration_date = (
                        SELECT MIN(expiration_date)
                        FROM product_lots
                        WHERE product_id = products.id
                        AND expiration_date >= CURDATE()
                        ) LIMIT 1) AS lote_quantity'),
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
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) AS total_group_sales'),
            // Agregar esta línea para sumar los sales_average por group_id
            DB::raw('SUM(sales_average) OVER (PARTITION BY group_id) AS group_sales_average_sum'),
            DB::raw('(CASE 
                WHEN SUM(sales_average) OVER (PARTITION BY group_id) > 0 
                THEN sales_average / SUM(sales_average) OVER (PARTITION BY group_id) 
                ELSE 0 
                END) * 100 AS preferencia_product'),
        ];

        // calcular promedio en vace a los dias => promedio_calculado
        $promedio_calculado = "";
        if ($filtros["lapso_de_tiempo"] == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        }

        if ($filtros["lapso_de_tiempo"] == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        if ($filtros["lapso_de_tiempo"] == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        }

        if ($filtros["lapso_de_tiempo"] == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        }

        if ($filtros["lapso_de_tiempo"] == "1 year") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        }

        // calcular diferencia_product con promedio_calculado
        // $columnas[] = DB::raw('stock - (' . $promedio_calculado . ') AS diferencia_product');

        // calculando preferencia formula: promedio_calculado * 100 = preferencia de producto
        // $columnas[] = DB::raw('(' . $promedio_calculado . ') * 100 AS preferencia_product');

        // calcular solicitar
        $columnas[] = DB::raw('stock - (' . $promedio_calculado . ') AS solicitar');

        // calcular demanda_ajustada con promedio_calculado
        $columnas[] =  DB::raw('COALESCE(
                (SELECT quantity FROM product_lots 
                WHERE product_id = products.id
                AND expiration_date = (
                    SELECT MIN(expiration_date)
                    FROM product_lots
                    WHERE product_id = products.id
                    AND expiration_date >= CURDATE()
                ) LIMIT 1), 0) - 
                ((' . $promedio_calculado . ') * 
                COALESCE((SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date))
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND expiration_date >= CURDATE()), 0)
            ) AS demanda_ajustada');


        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"]);

        if (array_key_exists("tipo_vista", $filtros)) {
            if ($filtros["tipo_vista"] == true) {
                $consulta->has("group");
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
            $consulta->where("laboratory_id", "=", $filtros["laboratoryId"]);
        }

        if (array_key_exists("groups", $filtros)) {
            if (count($filtros["groups"]) > 0) {
                $consulta->whereIn("group_id", $filtros["groups"]);
            }
        }


        // if (array_key_exists("expProd", $filtros)) {
        //     if ($filtros["expProd"] == true) {
        //         $consulta->having("demanda_ajustada", ">", 0);
        //     }
        // }

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->where("stock", ">", 0);
            } else {
                $consulta->where("stock", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", ">", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", "<", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
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
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            "sale_price",
            "unit_cost",
            "psychotropic",
            "is_colombian_origin",
            "active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(SELECT quantity 
                    FROM product_lots 
                    WHERE product_id = products.id
                    AND expiration_date = (
                        SELECT MIN(expiration_date)
                        FROM product_lots
                        WHERE product_id = products.id
                        AND expiration_date >= CURDATE()
                        ) LIMIT 1) AS lote_quantity'),
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
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
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
            DB::raw(' stock - ' . $ventasIndividualDelProducto . '  AS solicitar'),
        ];

        // calcular promedio en vace a los dias => promedio_calculado
        $promedio_calculado = "";
        if ($filtros["lapso_de_tiempo"] == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        }

        if ($filtros["lapso_de_tiempo"] == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        if ($filtros["lapso_de_tiempo"] == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        }

        if ($filtros["lapso_de_tiempo"] == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        }

        if ($filtros["lapso_de_tiempo"] == "1 year") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        }

        // calcular diferencia_product con promedio_calculado
        // $columnas[] = DB::raw('stock - (' . $promedio_calculado . ') AS diferencia_product');

        // calcular demanda_ajustada con promedio_calculado
        $columnas[] =  DB::raw('COALESCE(
                (SELECT quantity FROM product_lots 
                WHERE product_id = products.id
                AND expiration_date = (
                    SELECT MIN(expiration_date)
                    FROM product_lots
                    WHERE product_id = products.id
                    AND expiration_date >= CURDATE()
                ) LIMIT 1), 0) - 
                ((' . $promedio_calculado . ') * 
                COALESCE((SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date))
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND expiration_date >= CURDATE()), 0)
            ) AS demanda_ajustada');


        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"]);

        if (array_key_exists("tipo_vista", $filtros)) {
            if ($filtros["tipo_vista"] == true) {
                $consulta->has("group");
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
            $consulta->where("laboratory_id", "=", $filtros["laboratoryId"]);
        }

        if (array_key_exists("groups", $filtros)) {
            if (count($filtros["groups"]) > 0) {
                $consulta->whereIn("group_id", $filtros["groups"]);
            }
        }

        // if (array_key_exists("expProd", $filtros)) {
        //     if ($filtros["expProd"] == true) {
        //         $consulta->having("demanda_ajustada", ">", 0);
        //     }
        // }

        if (array_key_exists("hasStock", $filtros)) {
            if ($filtros["hasStock"] == true) {
                $consulta->where("stock", ">", 0);
            } else {
                $consulta->where("stock", "=", 0);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", ">", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", "<", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
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

    public function builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros): Builder
    {

        $columnas = [
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            "sale_price",
            "unit_cost",
            "psychotropic",
            "is_colombian_origin",
            "active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(SELECT quantity 
                    FROM product_lots 
                    WHERE product_id = products.id
                    AND expiration_date = (
                        SELECT MIN(expiration_date)
                        FROM product_lots
                        WHERE product_id = products.id
                        AND expiration_date >= CURDATE()
                        ) LIMIT 1) AS lote_quantity'),
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
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
            ) AS total_group_sales'),
            // Agregar esta línea para sumar los sales_average por group_id
            DB::raw('SUM(sales_average) OVER (PARTITION BY group_id) AS group_sales_average_sum'),
            DB::raw('(CASE 
                WHEN SUM(sales_average) OVER (PARTITION BY group_id) > 0 
                THEN sales_average / SUM(sales_average) OVER (PARTITION BY group_id) 
                ELSE 0 
                END) * 100 AS preferencia_product'),
        ];

        // calcular promedio en vace a los dias => promedio_calculado
        $promedio_calculado = "";
        if ($filtros["lapso_de_tiempo"] == "15 days") {
            $columnas[] = DB::raw('sales_average / 2 AS promedio_calculado');
            $promedio_calculado = 'sales_average / 2';
        }

        if ($filtros["lapso_de_tiempo"] == "1 month") {
            $columnas[] = DB::raw('sales_average AS promedio_calculado');
            $promedio_calculado = 'sales_average';
        }

        if ($filtros["lapso_de_tiempo"] == "3 month") {
            $columnas[] = DB::raw('sales_average * 3 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 3';
        }

        if ($filtros["lapso_de_tiempo"] == "6 month") {
            $columnas[] = DB::raw('sales_average * 6 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 6';
        }

        if ($filtros["lapso_de_tiempo"] == "12 month") {
            $columnas[] = DB::raw('sales_average * 12 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 12';
        }

        if ($filtros["lapso_de_tiempo"] == "18 month") {
            $columnas[] = DB::raw('sales_average * 18 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 18';
        }

        if ($filtros["lapso_de_tiempo"] == "24 month") {
            $columnas[] = DB::raw('sales_average * 24 AS promedio_calculado');
            $promedio_calculado = 'sales_average * 24';
        }

        // calcular solicitar
        $columnas[] = DB::raw('stock - (' . $promedio_calculado . ') AS solicitar');


        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"]);


        if (array_key_exists("is_colombia", $filtros)) {
            if ($filtros["is_colombia"] == true) {
                $consulta->where("is_colombian_origin", "=", 1);
            } else if ($filtros["is_colombia"] == false) {
                $consulta->where("is_colombian_origin", "=", 0);
            }
        }

        if (array_key_exists("product", $filtros)) {
            if (count($filtros["product"]) > 0) {
                $consulta->whereIn("id", $filtros["product"]);
            }
        }

        if (array_key_exists("laboratoryId", $filtros)) {
            if (count($filtros["laboratoryId"]) > 0) {
                $consulta->whereIn("laboratory_id", $filtros["laboratoryId"]);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", ">", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", "<", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
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
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            "sale_price",
            "unit_cost",
            "psychotropic",
            "is_colombian_origin",
            "active_ingredient",
            DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
             FROM product_lots 
             WHERE product_lots.product_id = products.id
             AND expiration_date >= CURDATE()) AS meses_faltantes'),
            DB::raw('(SELECT quantity 
                    FROM product_lots 
                    WHERE product_id = products.id
                    AND expiration_date = (
                        SELECT MIN(expiration_date)
                        FROM product_lots
                        WHERE product_id = products.id
                        AND expiration_date >= CURDATE()
                        ) LIMIT 1) AS lote_quantity'),
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
                AND o.created_at BETWEEN \'' . $filtros["previousDate"] . '\' AND \'' . $filtros["dateToday"] . '\'
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
            DB::raw(' stock - ' . $ventasIndividualDelProducto . '  AS solicitar'),
        ];

        $columnas[] = DB::raw('sales_average / ' . $ventasIndividualDelProducto . ' AS promedio_calculado');

        $consulta = Product::select($columnas)->with(["laboratory", "lots", "group"]);



        if (array_key_exists("is_colombia", $filtros)) {
            if ($filtros["is_colombia"] == true) {
                $consulta->where("is_colombian_origin", "=", 1);
            } else if ($filtros["is_colombia"] == false) {
                $consulta->where("is_colombian_origin", "=", 0);
            }
        }

        if (array_key_exists("product", $filtros)) {
            if (count($filtros["product"]) > 0) {
                $consulta->whereIn("id", $filtros["product"]);
            }
        }

        if (array_key_exists("laboratoryId", $filtros)) {
            if (count($filtros["laboratoryId"]) > 0) {
                $consulta->whereIn("laboratory_id", $filtros["laboratoryId"]);
            }
        }

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("solicitar", ">", 0);
            }
            if ($filtros["stock"] == "fallas") {
                $consulta->having("solicitar", "<", 0);
            }
        }

        if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
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

    public function filtrarIndividualProductForAssistantReportTypeAverageWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);

        return $consulta->get();
    }

    public function filtrarIndividualProductForAssistantReportTypeAverageWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);

        return $consulta->paginate($perPage);
    }

    public function filtrarIndividualProductForAssistantReportTypeSelesWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);

        return $consulta->get();
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);

        return $consulta->paginate($perPage);
    }
}
