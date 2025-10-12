<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Expenses;
use App\Contracts\Transaction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeStatusExpenseRequest;
use App\Http\Requests\CreateExpenseRecurrenceRequest;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\EditExpenseRequest;
use App\Http\Requests\UploadFileInvoiceExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpensesController extends Controller
{
    //TODO: 

    public function __construct(
        protected Expenses $expenses,
        protected Transaction $transaction
    ) {}


    public function createExpense(CreateExpenseRequest $request): JsonResponse
    {
        // dd($request->data->toArray());
        $expense = $this->expenses->crearGasto($request->data);

        return ApiResponse::success($expense, "ok");
    }

    public function createExpenseRecurrente(CreateExpenseRecurrenceRequest $request): JsonResponse
    {
        $expense = $this->expenses->crearGastoRecurrente($request->data);

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

        if ($request->filled("category_id_filtro")) {
            $filtros["category_id_filtro"] = $request->category_id_filtro;
        }

        if ($request->filled("currency")) {
            $filtros["currency"] = $request->currency;
        }

        if ($request->filled("status")) {
            $filtros["status"] = $request->status;
        }

        if ($request->filled("type_of_expense")) {
            $filtros["type_of_expense"] = $request->type_of_expense;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $respuestaConsulta = $this->expenses->filterWithPaginate($filtros, $filtros["itemsPerPage"]);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function filterWithoutPaginate(Request $request): JsonResponse
    {

        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("category_id_filtro")) {
            $filtros["category_id_filtro"] = $request->category_id_filtro;
        }

        if ($request->filled("currency")) {
            $filtros["currency"] = $request->currency;
        }

        if ($request->filled("status")) {
            $filtros["status"] = $request->status;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("type_of_expense")) {
            $filtros["type_of_expense"] = $request->type_of_expense;
        }

        $respuestaConsulta = $this->expenses->filterWithoutPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }


    public function exportExcel(Request $request): BinaryFileResponse
    {

        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("category_id_filtro")) {
            $filtros["category_id_filtro"] = $request->category_id_filtro;
        }

        if ($request->filled("currency")) {
            $filtros["currency"] = $request->currency;
        }

        if ($request->filled("status")) {
            $filtros["status"] = $request->status;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("type_of_expense")) {
            $filtros["type_of_expense"] = $request->type_of_expense;
        }

        $excel = $this->expenses->exportExcel($filtros);

        $fileName = 'gastos-pendientes-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }

    public function changeStatus(ChangeStatusExpenseRequest $request): JsonResponse
    {

        $respuestaConsulta = $this->expenses->changeStatus($request->data->id, $request->data->status);
        $expense = $this->expenses->consultById($request->data->id);

        if ($expense->count == "Tarjeta") {
            $expense->count = "CARD";
        } else if ($expense->count == "Efectivo") {
            $expense->count = "CASH";
        } else if ($expense->count == "Transferencia") {
            $expense->count = "TRANSFER";
        } else if ($expense->count == "Pago Móvil") {
            $expense->count = "MOBILE";
        } else if ($expense->count == "Binance") {
            $expense->count = "BINANCE";
        } else if ($expense->count == "PayPal") {
            $expense->count = "PAYPAL";
        }

        if ($request->data->status == Expense::STATUS_APPROVED) {
            $transaction = $this->transaction->createTransactionSalida($expense);
        }


        return ApiResponse::success($respuestaConsulta, "ok", 200);
    }

    public function uploadFileInvoice(UploadFileInvoiceExpenseRequest $request): JsonResponse
    {

        $respuestaConsulta = $this->expenses->cargarFactura($request->data->toArray());

        return ApiResponse::success(null, "ok", 200);
    }
}
