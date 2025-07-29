<?php


namespace App\Repository;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository
{





    public function builerFiltrarProductforStock($filtros): Builder
    {
        // $consulta = Product::select([
        //     'id',
        //     'name',
        //     'stock',
        //     'group_id',
        //     'laboratory_id',
        //     "sales_average",
        //     DB::raw('stock / NULLIF(
        //         (SELECT COUNT(*) FROM products AS p2 WHERE p2.group_id = products.group_id), 
        //     0) AS preferencia_product'),
        //     DB::raw('stock - sales_average AS diferencia_product'),
        //     // DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MAX(expiration_date)) 
        //     //  FROM product_lots 
        //     //  WHERE product_lots.product_id = products.id) AS meses_faltantes')
        //     DB::raw('(SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date)) 
        //      FROM product_lots 
        //      WHERE product_lots.product_id = products.id
        //      AND expiration_date >= CURDATE()) AS meses_faltantes'),
        //     DB::raw('(SELECT pl.quantity 
        //      FROM product_lots pl
        //      WHERE pl.product_id = products.id
        //      AND pl.expiration_date = (
        //          SELECT MIN(expiration_date)
        //          FROM product_lots
        //          WHERE product_id = products.id
        //          AND expiration_date >= CURDATE()
        //      )
        //      LIMIT 1) AS lote_quantity')
        // ])->with(["laboratory", "lots"]);

        $consulta = Product::select([
            'id',
            'name',
            'stock',
            'group_id',
            'laboratory_id',
            "sales_average",
            DB::raw('stock - sales_average AS diferencia_product'),
            DB::raw('stock / NULLIF(
                (SELECT COUNT(*) FROM products AS p2 WHERE p2.group_id = products.group_id), 
            0) AS preferencia_product'),
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
            DB::raw('COALESCE(
        (SELECT quantity FROM product_lots 
         WHERE product_id = products.id
         AND expiration_date = (
             SELECT MIN(expiration_date)
             FROM product_lots
             WHERE product_id = products.id
             AND expiration_date >= CURDATE()
         ) LIMIT 1), 0) - 
        (sales_average * 
        COALESCE((SELECT TIMESTAMPDIFF(MONTH, CURDATE(), MIN(expiration_date))
         FROM product_lots 
         WHERE product_lots.product_id = products.id
         AND expiration_date >= CURDATE()), 0)
    ) AS demanda_ajustada')
        ])->with(["laboratory", "lots"]);

        $consulta->selectSub(function ($query) {
            $query->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
                ->from('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->whereColumn('order_details.product_id', 'products.id')
                ->where('orders.status', 'Completed');
        }, 'total_sold_completed');

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

        if (array_key_exists("stock", $filtros)) {

            if ($filtros["stock"] == "exceso") {
                $consulta->having("diferencia_product", "<", 0);
            }
            if ($filtros["stock"] == "faltas") {
                $consulta->having("diferencia_product", ">", 0);
            }
        }

        if (array_key_exists("expirationDays", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["dateToday"], $filtros["expirationDate"]]);
                if (array_key_exists("startDate", $filtros) && array_key_exists("endDate", $filtros)) {
                    if ($filtros["startDate"] != "" && $filtros["endDate"] != "") {
                        $query->whereBetween("expiration_date", [$filtros["startDate"], $filtros["endDate"]]);
                    }
                }
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
}
