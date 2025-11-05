<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AutoOrder;
use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Helpers\Algoritmo;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product as ModelsProduct;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppliersIaOrderAssistantController extends Controller
{
    //

    public function __construct(
        protected Product $product,
        protected ProductSupplier $productSupplier,
        protected AutoOrder $autoOrder
    ) {}


    public function filtrarPaginate(Request $request): JsonResponse
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

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtros["groups"] = $request->groups;
        }


        if ($request->filled("lapso_de_tiempo")) {
            $timeZone = new DateTimeZone(env("APP_TIMEZONE"));
            $dateToday = new DateTime("now", $timeZone);
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $previousDate = new DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($respuesta["tipo_filtracion"] == "average") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
        }
        if ($respuesta["tipo_filtracion"] == "sales") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeSales($filtros);
        } else {

            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
            // $filtros["orderBy"] = "ASC";
            // $filtros["sortBy"] = "id";
            // $respuestaConsulta2 = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }

        $respuesta["paginate"]->each(function ($items) use ($filtros) {
            $items = $this->product->calcularAOProduct($items);
            $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;
            if ($filtros["tipo_filtracion"] != "average" && $filtros["tipo_filtracion"] != "sales") {
                $filtros["orderBy"] = "ASC";
                $filtros["sortBy"] = "id";
                $filtros["id"] =  $items->id;
                $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
                if ($itemsBusqueda) {
                    $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
                    $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
                    $items->solicitar = ceil(($items->solicitar + $itemsBusqueda->solicitar) / 2);
                }
            }
        });

        // dd($respuesta["paginate"]->items());

        // $respuesta["paginate"]->items = $this->product->calcularAOProduct(collect($respuesta["paginate"]->data));


        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function generateListProductoToRequest(Request $request): JsonResponse
    {
        $timeZone = new DateTimeZone(env("APP_TIMEZONE"));
        $dateToday = new DateTime("now", $timeZone);
        $respuesta = [
            "listaDeProductos" => [],
            "productos" => [],
            "productosFallas" => [],
            "productos_a_reponer" => [],
            "productos_oportunidad_unica" => [],
        ];

        $productosFallas = null;
        $filtrosFallas = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
            "laboratoryId"      => $request->laboratoryId,
            "groups"            => $request->groups,
            "stock"             => "fallas",
        ];


        if ($request->filled("laboratoryId")) {
            $filtrosFallas["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtrosFallas["groups"] = $request->groups;
        }


        if ($request->filled("lapso_de_tiempo")) {
            $filtrosFallas["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtrosFallas["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtrosFallas["dateToday"] = $dateToday->format("Y-m-d");
            $filtrosFallas["previousDate"] = $this->generarPreviousDate($filtrosFallas["tiempo"], $filtrosFallas["tipo_de_tiempo"]);
        }



        if ($filtrosFallas["tipo_filtracion"] == "average") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosFallas);
        } else if ($filtrosFallas["tipo_filtracion"] == "sales") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosFallas);
        } else {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosFallas);
        }

        if ($productosFallas == null) {
            return ApiResponse::error("Por favor pase un tipo de filtro average o sales", 400);
        }

        $productosFallas = $this->product->calcularAOProducts($productosFallas);

        $productosFallas = $this->product->removerProductosConPedidosAutomaticos($productosFallas);

        $productosFallas = $this->product->actualizarElSolicitadoConElAO($productosFallas);


        if ($filtrosFallas["tipo_filtracion"] != "average" && $filtrosFallas["tipo_filtracion"] != "sales") {
            for ($index = 0; $index < count($productosFallas); $index++) {
                # code...
                $filtros["orderBy"] = "ASC";
                $filtros["sortBy"] = "id";
                $filtros["id"] =  $productosFallas[$index]->id;
                $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosFallas)->first();
                if ($itemsBusqueda) {
                    $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
                    $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
                    $productosFallas[$index]->solicitar = ceil(($productosFallas[$index]->solicitar + $itemsBusqueda->solicitar) / 2);
                }
            }
        }



        $respuesta["productos_a_reponer"] = $this->productSupplier->getSupplierToReplenishTheProducts($productosFallas, $request->con_descuento);
        $respuesta["productos_a_reponer"] = $this->productSupplier->checkTolerance($respuesta["productos_a_reponer"], $request->con_descuento);
        $respuesta["productosFallas"] = $productosFallas;
        // codigo para listar porductos que tengan oportunidad unica de mercado
        $productos = null;

        $filtrosConExistencia = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => "1 year",
            // "stock"             => "all",
            "dateToday"         => null,
            "previousDate"      => null,
            "orderBy"           => "asc",
            "sortBy"            => "name",
        ];

        if ($request->filled("laboratoryId")) {
            $filtrosConExistencia["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtrosConExistencia["groups"] = $request->groups;
        }

        $filtrosConExistencia["dateToday"] =  $dateToday->format("Y-m-d h:m:s");
        $filtrosConExistencia["previousDate"] = $this->generarPreviousDate("1", "year");


        if ($filtrosConExistencia["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosConExistencia);
        }
        if ($filtrosConExistencia["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosConExistencia);
        } else {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosConExistencia);
        }

        $productos = $this->product->calcularAOProducts($productos);


        $productos = $this->product->removerProductosConPedidosAutomaticos($productos);


        $productos = $this->product->actualizarElSolicitadoConElAO($productos);



        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->getSupplierToReplenishTheProductsWithoutValidateSolicitar($productos, $request->con_descuento);
        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->checkTolerance($respuesta["productos_oportunidad_unica"], $request->con_descuento);
        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($respuesta["productos_oportunidad_unica"]);




        // $items = $this->product->calcularAOProduct($items);
        // $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;




        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function generarPreviousDate($cantidad = "0", $tiempo = "days")
    {
        $timeZone = new DateTimeZone(env("APP_TIMEZONE"));
        $fecha = new DateTime("now", $timeZone);
        $fecha->modify("-" . $cantidad . " " . $tiempo);
        return $fecha->format("Y-m-d");
    }

    public function generarOrden(Request $request): JsonResponse
    {

        $listAutoOrders = $this->autoOrder->createMultiple($request->orders);

        return ApiResponse::success($listAutoOrders, "ok", 200);
    }

    public function consultarProductosSinProveedor(Request $request): JsonResponse
    {

        $timeZone = new DateTimeZone(env("APP_TIMEZONE"));
        $dateToday = new DateTime("now", $timeZone);

        $productos = null;
        $filtros = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
            // "lapso_de_tiempo"   => "1 year",
            "stock"             => "all",
            "dateToday"         => null,
            "previousDate"      => null,
            "orderBy"           => "asc",
            "sortBy"            => "name",
            "ids"               =>  $request->ids ?? null,

        ];

        // $idsProductos=[];

        if ($request->filled("lapso_de_tiempo")) {
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $this->generarPreviousDate($filtros["tiempo"], $filtros["tipo_de_tiempo"]);
        }


        if ($filtros["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }


        foreach ($request->idsConFantante as $key => $value) {

            for ($index = 0; $index < count($productos); $index++) {

                if ($productos[$index]->id == $value["id"]) {
                    $productos[$index]->stockFaltante = $value["solicitar"];
                }
            }
        }

        // $productos = $this->productSupplier->getTheLowestLotCost($productos);

        return ApiResponse::success($productos, "ok", 200);
    }
}
