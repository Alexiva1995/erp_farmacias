<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryStockController extends Controller
{
    //

    public function __construct(
        protected Product $product
    ) {
    }

    public function filter(Request $request)
    {

        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];

        if ($request->filled("q")) {
            $filtros["q"] = $request->q;
        }

        if ($request->filled("hasStock")) {
            $filtros["hasStock"] = $request->hasStock;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
        }

        if ($request->filled("expProd")) {
            $filtros["expProd"] = $request->expProd;
        }


        if ($request->filled("startDate") && $request->filled("endDate")) {
            $filtros["startDate"] = $request->startDate;
            $filtros["endDate"] = $request->endDate;
        }


        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("days")) {
            $timeZone = new DateTimeZone(config("app.timezone"));
            $dateToday = new DateTime("now", $timeZone);
            $filtros["days"] = $request->days;
            $previousDate = new DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($request->filled("isStrictSearch")) {
            $filtros["isStrictSearch"] = filter_var($request->isStrictSearch, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("tipo_filtracion")) {
            $filtros["tipo_filtracion"] = $request->tipo_filtracion;
        } else {
            $filtros["tipo_filtracion"] = "average"; // Valor por defecto
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);
        }

        // dd($filtros);

        $respuestaConsulta = $this->product->filtrarStock($filtros);
        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function filterWithoutPaginate(Request $request)
    {

        $filtros = [];

        if ($request->filled("q")) {
            $filtros["q"] = $request->q;
        }

        if ($request->filled("hasStock")) {
            $filtros["hasStock"] = $request->hasStock;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
        }

        if ($request->filled("expProd")) {
            $filtros["expProd"] = $request->expProd;
        }



        if ($request->filled("startDate") && $request->filled("endDate")) {
            $filtros["startDate"] = $request->startDate;
            $filtros["endDate"] = $request->endDate;
        }


        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("days")) {
            $timeZone = new DateTimeZone(config("app.timezone"));
            $dateToday = new DateTime("now", $timeZone);
            $filtros["days"] = $request->days;
            $previousDate = new DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($request->filled("isStrictSearch")) {
            $filtros["isStrictSearch"] = filter_var($request->isStrictSearch, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("tipo_filtracion")) {
            $filtros["tipo_filtracion"] = $request->tipo_filtracion;
        } else {
            $filtros["tipo_filtracion"] = "average"; // Valor por defecto
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);
        }

        // dd($filtros);

        $respuestaConsulta = $this->product->filtrarStockWithoutPaginate($filtros);
        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    function exportarExcel(Request $request)
    {

        $filtros = [];

        if ($request->filled("q")) {
            $filtros["q"] = $request->q;
        }

        if ($request->filled("hasStock")) {
            $filtros["hasStock"] = $request->hasStock;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
        }

        if ($request->filled("expProd")) {
            $filtros["expProd"] = $request->expProd;
        }

        if ($request->filled("startDate") && $request->filled("endDate")) {
            $filtros["startDate"] = $request->startDate;
            $filtros["endDate"] = $request->endDate;
        }


        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("days")) {
            $timeZone = new DateTimeZone(config("app.timezone"));
            $dateToday = new DateTime("now", $timeZone);
            $filtros["days"] = (int) $request->days;
            $previousDate = new DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($request->filled("isStrictSearch")) {
            $filtros["isStrictSearch"] = filter_var($request->isStrictSearch, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("tipo_filtracion")) {
            $filtros["tipo_filtracion"] = $request->tipo_filtracion;
        } else {
            $filtros["tipo_filtracion"] = "average"; // Valor por defecto
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);
        }

        $excel = $this->product->exportExcel($filtros);

        $fileName = 'stock-product-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
