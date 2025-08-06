<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryStockController extends Controller
{
    //

    public function __construct(
        protected Product $product
    ) {}

    public function filter(Request $request)
    {

        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
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
            $dateToday = new DateTime("now");
            $filtros["days"] = $request->days;
            $previousDate = new DateTime("now");
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
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
            $dateToday = new DateTime("now");
            $filtros["days"] = $request->days;
            $previousDate = new DateTime("now");
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
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
            $dateToday = new DateTime("now");
            $filtros["days"] = (int)$request->days;
            $previousDate = new DateTime("now");
            $previousDate->modify("-" . $filtros["days"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        $excel = $this->product->exportExcel($filtros);

        $fileName = 'stock-product-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }
}
