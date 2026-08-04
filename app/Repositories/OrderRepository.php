<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository implements \App\Contracts\Order
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
        $consulta = Order::query()->with([
            "client:id,name,last_name,identification_type,identification,phone",
            "seller:id,username"
        ])->where('orders.status', Order::COMPLETED);

        if (array_key_exists("minimo", $filtros) && is_numeric($filtros["minimo"])) {
            $consulta->where("orders.total_amount_usd", ">=", (float)$filtros["minimo"]);
        }

        if (array_key_exists("laboratory_id", $filtros) && !empty($filtros["laboratory_id"])) {
            $labs = is_array($filtros["laboratory_id"]) ? $filtros["laboratory_id"] : [$filtros["laboratory_id"]];
            $consulta->whereHas('details.product', function ($query) use ($labs) {
                $query->whereIn('laboratory_id', $labs);
            });
        }

        if (array_key_exists("dish_id", $filtros) && !empty($filtros["dish_id"])) {
            $dishes = is_array($filtros["dish_id"]) ? $filtros["dish_id"] : [$filtros["dish_id"]];
            $consulta->whereHas('details', function ($query) use ($dishes) {
                $query->whereIn('dish_id', $dishes);
            });
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if (!empty($filtros["fechaDesde_filtro"]) && !empty($filtros["fechaHasta_filtro"])) {
                $fechaDesde = $filtros["fechaDesde_filtro"] . (strlen($filtros["fechaDesde_filtro"]) === 10 ? ' 00:00:00' : '');
                $fechaHasta = $filtros["fechaHasta_filtro"] . (strlen($filtros["fechaHasta_filtro"]) === 10 ? ' 23:59:59' : '');
                $consulta->whereBetween("orders.created_at", [
                    $fechaDesde,
                    $fechaHasta
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
            $consulta->orderBy("orders.id", "DESC");
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
