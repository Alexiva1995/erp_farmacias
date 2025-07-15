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


    public function filtrarOrdenes(Request $request)
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


        $responsiveDB = $this->lottery->filterOrders($filtros);

        return ApiResponse::success($responsiveDB, "OK", 200);
    }
}
