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

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
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
