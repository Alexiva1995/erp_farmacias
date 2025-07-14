<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Lottery;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LotteryController extends Controller
{
    //

    public function __construct(
        protected Lottery $lottery
    ) {}

    public function filtrarOrdenesWithoutPaginate(Request $request)
    {
        $filtros = [];


        if ($request->filled("minimo")) {
            $filtros["minimo"] = $request->minimo;
        }

        if ($request->filled("laboratory_id")) {
            $filtros["laboratory_id"] = $request->laboratory_id;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        $respuesta = $this->lottery->filterOrdersWithoutPaginate($filtros);

        return ApiResponse::success($respuesta, "OK", 200);
    }


    public function filtrarOrdenesPaginate(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
        ];


        if ($request->filled("minimo")) {
            $filtros["minimo"] = $request->minimo;
        }

        if ($request->filled("laboratory_id")) {
            $filtros["laboratory_id"] = $request->laboratory_id;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        $paginate = $this->lottery->filterOrdersPaginate($filtros, $filtros["itemsPerPage"]);

        return ApiResponse::success($paginate, "OK", 200);
    }
}
