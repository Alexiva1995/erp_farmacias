<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Expenses;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\EditExpenseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    //TODO: 

    public function __construct(
        protected Expenses $expenses
    ) {}


    public function createExpense(CreateExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->crearGasto($request->data->toArray());

        return ApiResponse::success($expense, "ok");
    }

    public function editExpense(EditExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->editarGasto($request->data->toArray());

        return ApiResponse::success($expense, "ok");
    }


    public function deleteById(Request $request): JsonResponse
    {
        $this->expenses->deleteById($request->id);

        $respuestaConsulta = $this->expenses->consultById($request->id);

        if ($respuestaConsulta) {
            return ApiResponse::error("El gasto no a sido eliminado", 400);
        }

        return ApiResponse::success($respuestaConsulta, "Gasto Eliminado", 200);
    }

    public function getAll(): JsonResponse
    {
        $respuestaConsulta = $this->expenses->consultAll();

        return ApiResponse::success($respuestaConsulta, "ok");
    }

    public function consultById(Request $request)
    {
        $respuestaConsulta = $this->expenses->consultById($request->id);

        if (!$respuestaConsulta) {
            return ApiResponse::error("El gasto no a sido encontrado", 404);
        }

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function filterWithPaginate(Request $request): JsonResponse
    {

        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page"         => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $respuestaConsulta = $this->expenses->filterWithPaginate($filtros, $filtros["itemsPerPage"]);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function changeStatus(Request $request): JsonResponse
    {
        return ApiResponse::success(null, "ok", 200);
    }
}
