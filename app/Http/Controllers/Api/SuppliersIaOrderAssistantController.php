<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;

class SuppliersIaOrderAssistantController extends Controller
{
    //

    public function __construct(
        protected Product $product,
        protected ProductSupplier $productSupplier
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

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
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

    public function generateListProductoToRequest(Request $request)
    {

        $respuesta = [
            "productos_a_reponer" => [],
            "lista_ofertas_porducto" => [],
        ];

        $productos = null;

        $filtros = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
            "stock"             => $request->stock,
        ];

        if ($request->filled("lapso_de_tiempo")) {
            $dateToday = new DateTime("now");
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $previousDate = new DateTime("now");
            $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }


        if ($filtros["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }

        if ($productos == null) {
            return ApiResponse::error("Por favor pase un tipo de filtro average o sales", 400);
        }

        for ($index = 0; $index < count($productos); $index++) {
            # code...
            $ofertas = $this->productSupplier->consultSupplierByProductWithBetterPrice($productos[$index]);
            $productos[$index]->ofertas = $ofertas;
            $productos[$index]->repuesto = 0;
            $productos[$index]->solicitar = ceil((int)$productos[$index]->solicitar);

            if ((int)$productos[$index]->solicitar < 0) {
                for ($index2 = 0; $index2 < count($ofertas); $index2++) {
                    # code...
                    $suma = (int)$productos[$index]->solicitar + $ofertas[$index2]->quantity;
                    if ($suma >= 0) {
                        $productos[$index]->repuesto = abs((int)$productos[$index]->solicitar);
                        $productos[$index]->solicitar = 0;
                        break;
                    } else if ($suma < 0) {
                        $productos[$index]->solicitar = (int)$suma;
                        $productos[$index]->repuesto += $ofertas[$index2]->quantity;
                    }
                }
            }
        }







        return ApiResponse::success($productos, "ok", 200);
    }
}
