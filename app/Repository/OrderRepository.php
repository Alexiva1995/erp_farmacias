<?php


namespace App\Repository;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository
{







    public function builerFiltrarOrdenesforLottery($filtros): Builder
    {
        $consulta = Order::query()->with(["client", "seller"]);

        if (array_key_exists("minimo", $filtros)) {
            $consulta->where("total_amount_usd", ">", $filtros["minimo"]);
        }

        if (array_key_exists("laboratory_id", $filtros)) {
            $consulta->select('orders.*')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->where('products.laboratory_id', "=", $filtros["laboratory_id"])
                ->distinct();
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("id", "ASC");
        }

        return  $consulta;
    }

    public function filtrarOrdenesforLotteryWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarOrdenesforLottery($filtros);
        return  $consulta->get();
    }


    public function filtrarOrdenesforLotteryWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarOrdenesforLottery($filtros);

        return $consulta->paginate($perPage);
    }
}
