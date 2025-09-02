<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AutoOrder;
use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product as ModelsProduct;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use DateTime;
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

    public function generateListProductoToRequest(Request $request): JsonResponse
    {
        $dateToday = new DateTime("now");
        $respuesta = [
            // "productos" => [],
            "productosFallas" => [],
            "productosExceso" => [],
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
        }
        if ($filtrosFallas["tipo_filtracion"] == "sales") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosFallas);
        }

        if ($productosFallas == null) {
            return ApiResponse::error("Por favor pase un tipo de filtro average o sales", 400);
        }

        $respuesta["productos_a_reponer"] = $this->productSupplier->getSupplierToReplenishTheProducts($productosFallas);
        $respuesta["productos_a_reponer"] = $this->productSupplier->checkTolerance($respuesta["productos_a_reponer"]);
        $respuesta["productosFallas"] = $productosFallas;
        // codigo para listar porductos que tengan oportunidad unica de mercado
        $productos = null;

        $filtrosConExistencia = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => "1 year",
            "stock"             => "all",
            "dateToday"         => null,
            "previousDate"      => null,
            "orderBy"           => "asc",
            "sortBy"            => "stock",
        ];

        if ($request->filled("laboratoryId")) {
            $filtrosConExistencia["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtrosConExistencia["groups"] = $request->groups;
        }

        $filtrosConExistencia["dateToday"] =  $dateToday->format("Y-m-d");
        $filtrosConExistencia["previousDate"] = $this->generarPreviousDate("1", "year");


        if ($filtrosConExistencia["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosConExistencia);
        }
        if ($filtrosConExistencia["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosConExistencia);
        }

        // $respuesta["productos"] = $productos;
        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->getSupplierToReplenishTheProducts($productos);
        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->checkTolerance($respuesta["productos_oportunidad_unica"]);
        $respuesta["productos_oportunidad_unica"] = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($respuesta["productos_oportunidad_unica"]);




        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function generarPreviousDate($cantidad = "0", $tiempo = "days")
    {
        $fecha = new DateTime("now");
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

        $dateToday = new DateTime("now");

        $productos = null;
        $filtros = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
            // "lapso_de_tiempo"   => "1 year",
            "stock"             => "all",
            "dateToday"         => null,
            "previousDate"      => null,
            "sin_proveedor"     => true,
            "orderBy"           => "asc",
            "sortBy"            => "name",

        ];


        // if ($request->filled("laboratoryId")) {
        //     $filtros["laboratoryId"] = $request->laboratoryId;
        // }

        // if ($request->filled("groups")) {
        //     $filtros["groups"] = $request->groups;
        // }


        if ($request->filled("lapso_de_tiempo")) {
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $this->generarPreviousDate($filtros["tiempo"], $filtros["tipo_de_tiempo"]);
        }


        if ($filtros["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }
        $productos = $this->productSupplier->getTheLowestLotCost($productos);

        return ApiResponse::success($productos, "ok", 200);
    }
}
