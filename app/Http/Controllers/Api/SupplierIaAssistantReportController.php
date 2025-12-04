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
    ) {
    }


    public function filtrarPaginate(Request $request): JsonResponse
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
            "tipo_filtracion" => $request->tipo_filtracion,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
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
            $timeZone = new DateTimeZone(config("app.timezone"));
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
        } else if ($filtros["tipo_filtracion"] == "combinado") { // combinar
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros);
        } else {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros);
        }

        // Procesar cada item para calcular el análisis
        $respuestaConsulta->each(function ($items) use ($filtros) {
            // Calcular AO (Auto Order)
            $items = $this->product->calcularAOProduct($items);
            if ($filtros["tipo_filtracion"] == "combinado") {
                // Obtener datos de ventas para este producto específico
                $filtrosVentas = $filtros;
                $filtrosVentas["id"] = $items->id;
                $itemVentas = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosVentas)->first();
                if ($itemVentas) {
                    // Calcular AO para el item de ventas también
                    $itemVentas = $this->product->calcularAOProduct($itemVentas);

                    // Obtener valores correctos para el cálculo
                    $ventasTotales = $itemVentas->total_sold_completed ?? 0; // Usar total_sold_completed
                    $promedio = $items->promedio_calculado ?? 0; // Usar promedio_calculado
                    $stockActual = $items->lote_quantity ?? 0; // Stock actual
                    $autoOrder = $items->totalQuantityInAutoOrder ?? 0; // Cantidad en auto order

                    // Fórmula: (ventas + promedio) / 2 - stock - AO
                    $resultado = (($ventasTotales + $promedio) / 2) - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis (como funciona en promedio)
                    // Si el resultado es negativo (falta producto), se muestra positivo
                    // Si el resultado es positivo (exceso de producto), se muestra negativo
                    $items->solicitar = -$resultado;
                } else {
                    // Si no hay datos de ventas, usar solo el promedio menos stock y AO
                    $promedio = $items->promedio_calculado ?? 0;
                    $stockActual = $items->lote_quantity ?? 0;
                    $autoOrder = $items->totalQuantityInAutoOrder ?? 0;

                    $resultado = $promedio - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis
                    $items->solicitar = -$resultado;
                }

                // Redondear el resultado hacia arriba para combinado (mantener el signo)
                $items->solicitar = $items->solicitar > 0 ? ceil($items->solicitar) : floor($items->solicitar);
            } else {
                // Para "average" y "sales", mantener la lógica original
                $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;
            }
            // $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;
            // if ($filtros["tipo_filtracion"] != "average" && $filtros["tipo_filtracion"] != "sales") {
            //     $filtros["orderBy"] = "ASC";
            //     $filtros["sortBy"] = "id";
            //     $filtros["id"] = $items->id;
            //     $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
            //     if ($itemsBusqueda) {
            //         $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
            //         $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
            //         $items->solicitar = ceil(($items->solicitar + $itemsBusqueda->solicitar) / 2);
            //     }
            // }
        });



        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function filtrarWithoutPaginate(Request $request): JsonResponse
    {
        $filtros = [
            "tipo_filtracion" => $request->tipo_filtracion,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
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
        } else if ($filtros["tipo_filtracion"] == "combinado") { // combinar
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        } else {
            $respuestaConsulta = $this->product->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        }


        if ($filtros["tipo_filtracion"] == "combinado") {
            for ($index = 0; $index < count($respuestaConsulta); $index++) {
                // Obtener datos de ventas para este producto específico
                $filtrosVentas = $filtros;
                $filtrosVentas["id"] = $respuestaConsulta[$index]->id;
                $itemVentas = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosVentas)->first();
                if ($itemVentas) {
                    // Calcular AO para el item de ventas también
                    $itemVentas = $this->product->calcularAOProduct($itemVentas);

                    // Obtener valores correctos para el cálculo
                    $ventasTotales = $itemVentas->total_sold_completed ?? 0; // Usar total_sold_completed
                    $promedio = $respuestaConsulta[$index]->promedio_calculado ?? 0; // Usar promedio_calculado
                    $stockActual = $respuestaConsulta[$index]->lote_quantity ?? 0; // Stock actual
                    $autoOrder = $respuestaConsulta[$index]->totalQuantityInAutoOrder ?? 0; // Cantidad en auto order

                    // Fórmula: (ventas + promedio) / 2 - stock - AO
                    $resultado = (($ventasTotales + $promedio) / 2) - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis (como funciona en promedio)
                    // Si el resultado es negativo (falta producto), se muestra positivo
                    // Si el resultado es positivo (exceso de producto), se muestra negativo
                    $respuestaConsulta[$index]->solicitar = -$resultado;
                } else {
                    // Si no hay datos de ventas, usar solo el promedio menos stock y AO
                    $promedio = $respuestaConsulta[$index]->promedio_calculado ?? 0;
                    $stockActual = $respuestaConsulta[$index]->lote_quantity ?? 0;
                    $autoOrder = $respuestaConsulta[$index]->totalQuantityInAutoOrder ?? 0;

                    $resultado = $promedio - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis
                    $respuestaConsulta[$index]->solicitar = -$resultado;
                }

                // Redondear el resultado hacia arriba para combinado (mantener el signo)
                $respuestaConsulta[$index]->solicitar = $respuestaConsulta[$index]->solicitar > 0 ? ceil($respuestaConsulta[$index]->solicitar) : floor($respuestaConsulta[$index]->solicitar);
            }
        }
        // if ($filtros["tipo_filtracion"] != "average" && $filtros["tipo_filtracion"] != "sales") {
        //     for ($index = 0; $index < count($respuestaConsulta); $index++) {
        //         $itemsBusqueda = null;
        //         # code...
        //         $filtros["orderBy"] = "ASC";
        //         $filtros["sortBy"] = "id";
        //         $filtros["id"] = $respuestaConsulta[$index]->id;
        //         $itemsBusqueda = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
        //         if ($itemsBusqueda) {
        //             $itemsBusqueda = $this->product->calcularAOProduct($itemsBusqueda);
        //             $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
        //             $respuestaConsulta[$index]->solicitar = ceil(($respuestaConsulta[$index]->solicitar + $itemsBusqueda->solicitar) / 2);
        //         }
        //     }
        // }

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
            "tipo_filtracion" => $request->tipo_filtracion,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
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
