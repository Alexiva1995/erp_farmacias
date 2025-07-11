<?php


namespace App\Repository;

use App\Models\Order;

class OrderRepository
{





    public function filtrarOrdenesforLottery($filtros)
    {
        $consulta = Order::query();

        if (array_key_exists("minimo", $filtros)) {
            $consulta->where("total_amont_usd", ">", $filtros["minimo"]);
        }


        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }







        return $consulta->get();
    }
}
