<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Order;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    //


    public function __construct(
        protected Order $order,
    ) {}



    public function filtrarOrderPorpsychotropicsConPaginacion(Request $request): JsonResponse
    {


        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
        ];


        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->order->filtrarOrdenesWithPsychotropicsforPaginate($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }
}
