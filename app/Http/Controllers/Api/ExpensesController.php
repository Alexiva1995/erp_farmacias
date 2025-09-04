<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Expenses;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExpenseRequest;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    //TODO: 

    public function __construct(
        protected Expenses $expenses
    ) {}


    public function createExpense(CreateExpenseRequest $request)
    {
        $expense = $this->expenses->crearGasto($request->data->toArray());
        return ApiResponse::success($expense, "ok");
    }


    public function getAll()
    {

        return ApiResponse::success(null, "ok");
    }
}
