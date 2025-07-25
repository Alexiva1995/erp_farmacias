<?php


namespace App\Repository;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository
{





    public function builerFiltrarProductforStock($filtros): Builder
    {
        $consulta = Product::select([
            'id',
            'name',
            'stock',
            'group_id',
            "sales_average",
            DB::raw('stock / NULLIF(
                (SELECT COUNT(*) FROM products AS p2 WHERE p2.group_id = products.group_id), 
            0) AS preferencia_product'),
            DB::raw('stock - sales_average AS diferencia_product')
        ]);

        $consulta->selectSub(function ($query) {
            $query->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
                ->from('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->whereColumn('order_details.product_id', 'products.id')
                ->where('orders.status', 'Completed');
        }, 'total_sold_completed');


        if (array_key_exists("expirationDays", $filtros)) {
            $consulta->whereHas("lots", function ($query) use ($filtros) {
                $query->whereBetween("expiration_date", [$filtros["dateToday"], $filtros["expirationDate"]]);
            });
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }


        return $consulta;
    }




    public function filtrarProductforStocktWithPaginate($filtros, $perPage = 10): LengthAwarePaginator
    {

        $consulta = $this->builerFiltrarProductforStock($filtros);

        return $consulta->paginate($perPage);
    }
}
