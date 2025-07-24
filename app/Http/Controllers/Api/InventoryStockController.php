<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Product;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
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

        $respuestaConsulta = $this->product->filtrarStock($filtros);
        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }
}
