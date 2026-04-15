<?php

namespace App\Repository;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function filtrarOrdenesWithPsychotropicsforPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = Order::query()->with(["client", "seller"]);

        $consulta->select('orders.*')
            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('products.psychotropic', "=", 1)
            ->distinct();

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        }

        return $consulta->paginate($perPage);
    }

    public function builerFiltrarOrdenesforLottery($filtros): Builder
    {
        $consulta = Order::query()->with(["client", "seller"]);

        if (array_key_exists("minimo", $filtros)) {
            $consulta->where("total_amount_usd", ">", $filtros["minimo"]);
        }

        if (array_key_exists("laboratory_id", $filtros) && !empty($filtros["laboratory_id"])) {
            $consulta->select('orders.*')
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'order_details.product_id', '=', 'products.id');

            if (is_array($filtros["laboratory_id"])) {
                $consulta->whereIn('products.laboratory_id', $filtros["laboratory_id"]);
            } else {
                $consulta->where('products.laboratory_id', "=", $filtros["laboratory_id"]);
            }

            $consulta->distinct();
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("orders.created_at", [
                    $filtros["fechaDesde_filtro"],
                    $filtros["fechaHasta_filtro"]
                ]);
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $sortBy = $filtros["sortBy"];
            if (in_array($sortBy, ['id', 'created_at', 'total_amount_usd'])) {
                $sortBy = "orders.{$sortBy}";
            }
            $consulta->orderBy($sortBy, $filtros["orderBy"]);
        } else {
            $consulta->orderBy("orders.id", "ASC");
        }

        return $consulta;
    }

    public function filtrarOrdenesforLotteryWithoutPaginate($filtros): Collection
    {
        $consulta = $this->builerFiltrarOrdenesforLottery($filtros);
        return $consulta->get();
    }

    public function filtrarOrdenesforLotteryWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerFiltrarOrdenesforLottery($filtros);
        return $consulta->paginate($perPage);
    }
}
