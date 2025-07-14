<?php


namespace App\Repository;

use App\Models\Order;

class OrderRepository
{





    public function filtrarOrdenesforLottery($filtros)
    {
        $consulta = Order::query();

        if (array_key_exists("minimo", $filtros)) {
            $consulta->where("total_amount_usd", ">", $filtros["minimo"]);
        }

        if (array_key_exists("laboratory_id", $filtros)) {
            $consulta->select('orders.*')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->where('products.laboratory_id', "=", $filtros["laboratory_id"])
                ->distinct();
            // ->with(['details.product' => function ($query) use ($filtros) {
            //     $query->where('laboratory_id', $filtros["laboratory_id"]);
            // }])
        }
        // $consulta->where("total_amont_usd", ">", $filtros["minimo"]);
        // $consulta->whereHas(function ($query) use ($filtros) {});

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }

        return $consulta->get();
    }
}
