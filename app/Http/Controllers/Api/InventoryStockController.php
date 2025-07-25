<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;

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

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("expirationDays")) {
            $dateToday = new DateTime("now");
            $filtros["expirationDays"] = $request->expirationDays;
            $expirationDate = new DateTime("now");
            $expirationDate->modify("+" . $filtros["expirationDays"] . " days");
            $filtros["dateToday"] = $dateToday->format("Y-m-d");
            $filtros["expirationDate"] = $expirationDate->format("Y-m-d");
        }

        // dd($filtros);

        $respuestaConsulta = $this->product->filtrarStock($filtros);
        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }
}
