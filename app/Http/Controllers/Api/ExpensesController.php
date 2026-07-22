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
use App\Http\Resources\ExpenseResource;
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
    ) {
    }


    public function createExpense(CreateExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->data);

        if ($expense->status === \App\Enums\ExpenseStatus::APPROVED->value) {
            // Mapeo de cuenta usando Enum para la transacción
            $paymentMethod = \App\Enums\ExpensePaymentMethod::fromOldLabel($expense->count);
            $expense->count = $paymentMethod->value;
            
            $this->transaction->createTransactionSalida($expense);
        }

        return ApiResponse::success(new ExpenseResource($expense), "ok");
    }

    /*public function createExpenseRecurrente(CreateExpenseRecurrenceRequest $request): JsonResponse
    {
        $expense = $this->expenses->crearGastoRecurrente($request->data);

        return ApiResponse::success($expense, "ok");
    }*/

    public function editExpense(EditExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->update($request->data->toArray());

        return ApiResponse::success(new ExpenseResource($expense), "ok");
    }


    public function deleteById(Request $request): JsonResponse
    {
        $this->expenses->deleteById($request->id);

        return ApiResponse::success(null, "Gasto Eliminado", 200);
    }

    public function getAll(): JsonResponse
    {
        $expenses = $this->expenses->getAll();

        return ApiResponse::success(ExpenseResource::collection($expenses), "ok");
    }

    public function consultById(Request $request): JsonResponse
    {
        $expense = $this->expenses->findById($request->id);

        if (!$expense) {
            return ApiResponse::error("El gasto no ha sido encontrado", 404);
        }

        return ApiResponse::success(new ExpenseResource($expense), "ok", 200);
    }


    public function filterWithPaginate(Request $request): JsonResponse
    {
        $filters = $request->only([
            'itemsPerPage', 'page', 'buscardor_filtro', 'category_id_filtro',
            'currency', 'status', 'fechaDesde_filtro', 'fechaHasta_filtro',
            'hasInvoice', 'is_deductible', 'orderBy', 'sortBy'
        ]);

        $expenses = $this->expenses->filterWithPaginate($filters, $filters["itemsPerPage"] ?? 10);

        return ApiResponse::success(ExpenseResource::collection($expenses)->response()->getData(true), "ok", 200);
    }

    public function filterWithoutPaginate(Request $request): JsonResponse
    {
        $filters = $request->only([
            'buscardor_filtro', 'category_id_filtro', 'currency', 'status',
            'fechaDesde_filtro', 'fechaHasta_filtro', 'type_of_expense'
        ]);

        $expenses = $this->expenses->filterWithoutPaginate($filters);

        return ApiResponse::success(ExpenseResource::collection($expenses), "ok", 200);
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
        $expense = $this->expenses->findById($request->id);
        
        if (!$expense) {
            return ApiResponse::error("Gasto no encontrado", 404);
        }

        $updatedExpense = $this->expenses->updateStatus($request->id, $request->status);

        if ($request->status === \App\Enums\ExpenseStatus::APPROVED->value) {
            // Mapeo de cuenta usando Enum para la transacción
            $paymentMethod = \App\Enums\ExpensePaymentMethod::fromOldLabel($updatedExpense->count);
            $updatedExpense->count = $paymentMethod->value;
            
            $this->transaction->createTransactionSalida($updatedExpense);
        }

        return ApiResponse::success(null, "Estado actualizado con éxito", 200);
    }

    public function uploadFileInvoice(UploadFileInvoiceExpenseRequest $request): JsonResponse
    {
        $this->expenses->uploadInvoice($request->data->toArray());

        return ApiResponse::success(null, "Factura cargada", 200);
    }

    public function getStats(Request $request): JsonResponse
    {
        $filters = $request->only([
            'buscardor_filtro', 'category_id_filtro', 'currency', 'fechaDesde_filtro', 'fechaHasta_filtro'
        ]);

        $stats = $this->expenses->getGlobalStats($filters);

        return ApiResponse::success($stats, "ok", 200);
    }
}
