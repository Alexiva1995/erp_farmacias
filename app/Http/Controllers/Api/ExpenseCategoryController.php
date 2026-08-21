<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ExpenseCategory;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        protected ExpenseCategory $expenseCategory
    ) {}

    public function getAll(): JsonResponse
    {
        $categories = $this->expenseCategory->getAll();

        return ApiResponse::success(
            ExpenseCategoryResource::collection($categories),
            "Categorías de gastos obtenidas exitosamente"
        );
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = $this->expenseCategory->create($request->validated());

        return ApiResponse::success(
            new ExpenseCategoryResource($category),
            "Categoría de gasto creada exitosamente",
            201
        );
    }

    public function update(UpdateExpenseCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->expenseCategory->update($id, $request->validated());

        return ApiResponse::success(
            new ExpenseCategoryResource($category),
            "Categoría de gasto actualizada exitosamente"
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->expenseCategory->delete($id);

        return ApiResponse::success(
            null,
            "Categoría de gasto eliminada exitosamente"
        );
    }
}

