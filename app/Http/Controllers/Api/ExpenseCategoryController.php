<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ExpenseCategory;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    //

    public function __construct(
        protected ExpenseCategory $expenseCategory
    ) {}

    public function getAll(): JsonResponse
    {
        $respuestaConsulta = $this->expenseCategory->getAll();

        return ApiResponse::success($respuestaConsulta, "ok");
    }
}
