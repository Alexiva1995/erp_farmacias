<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;

class SuppliersIaOrderAssistantController extends Controller
{
    //

    public function __construct(
        protected Product $product
    ) {}


    public function filtrarPaginate(Request $request)
    {
        $respuesta = [
            "tipo_filtracion"  => $request->tipo_filtracion,
            "tipo_vista"       => $request->tipo_vista,
            "paginate"         => [],
        ];

        $filtros = [
            "itemsPerPage"      => $request->itemsPerPage,
            "page"              => $request->page,
            "tipo_filtracion"   => $request->tipo_filtracion,
            "tipo_vista"        => $request->tipo_vista,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
        ];

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }


        if ($request->filled("lapso_de_tiempo")) {
            $dateToday = new DateTime("now");
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $previousDate = new DateTime("now");
            $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($respuesta["tipo_filtracion"] == "average") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
        }
        if ($respuesta["tipo_filtracion"] == "sales") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeSales($filtros);
        }

        return ApiResponse::success($respuesta, "ok", 200);
    }
}
