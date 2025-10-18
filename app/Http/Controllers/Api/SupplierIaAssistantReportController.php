<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\Algoritmo;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SupplierIaAssistantReportController extends Controller
{
    //

    public function __construct(
        protected Product $product
    ) {}


    public function filtrarPaginate(Request $request): JsonResponse
    {
        $filtros = [
            "itemsPerPage"      => $request->itemsPerPage,
            "page"              => $request->page,
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
        ];

        if ($request->filled("product")) {
            $filtros["product"] = $request->product;
        }

        if ($request->filled("is_colombia")) {
            $filtros["is_colombia"] = $request->is_colombia;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
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

        $respuestaConsulta = null;

        if ($filtros["tipo_filtracion"] == "average") {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros);
        } else if ($filtros["tipo_filtracion"] == "sales") {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithPaginate($filtros);
        } else { // combinar
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros);
            $filtros["orderBy"] = "ASC";
            $filtros["sortBy"] = "id";
        }


        $respuestaConsulta->each(function ($items) use ($filtros) {
            $items = $this->product->calcularAOProduct($items);
            $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;
            $filtros["orderBy"] = "ASC";
            $filtros["sortBy"] = "id";
            $filtros["id"] = $items->id;
            $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
            if ($itemsBusqueda) {
                $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
                $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
                $items->solicitar = ceil(($items->solicitar + $itemsBusqueda->solicitar) / 2);
            }
        });



        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function filtrarWithoutPaginate(Request $request): JsonResponse
    {
        $filtros = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
        ];

        if ($request->filled("product")) {
            $filtros["product"] = $request->product;
        }

        if ($request->filled("is_colombia")) {
            $filtros["is_colombia"] = $request->is_colombia;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
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

        $respuestaConsulta = null;


        if ($filtros["tipo_filtracion"] == "average") {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        } else if ($filtros["tipo_filtracion"] == "sales") {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros);
        } else { // combinar
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        }


        if ($filtros["tipo_filtracion"] != "average" && $filtros["tipo_filtracion"] != "sales") {
            for ($index = 0; $index < count($respuestaConsulta); $index++) {
                $itemsBusqueda = null;
                # code...
                $filtros["orderBy"] = "ASC";
                $filtros["sortBy"] = "id";
                $filtros["id"] = $respuestaConsulta[$index]->id;
                $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
                if ($itemsBusqueda) {
                    $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
                    $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
                    $respuestaConsulta[$index]->solicitar = ceil(($respuestaConsulta[$index]->solicitar + $itemsBusqueda->solicitar) / 2);
                }
            }
        }

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function consultProduct(): JsonResponse
    {
        $respuesta = $this->product->consultProduct();
        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function exportarExcel(Request $request)
    {

        $filtros = [
            "tipo_filtracion"   => $request->tipo_filtracion,
            "lapso_de_tiempo"   => $request->lapso_de_tiempo,
        ];

        if ($request->filled("product")) {
            $filtros["product"] = $request->product;
        }

        if ($request->filled("is_colombia")) {
            $filtros["is_colombia"] = $request->is_colombia;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
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

        $excel = $this->product->exportAssistantReportExcel($filtros);
        $fileName = 'assistant-report-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
