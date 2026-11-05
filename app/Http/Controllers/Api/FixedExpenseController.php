<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\FixedExpenses;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListFixedExpensesRequest;
use App\Http\Requests\PayFixedExpenseOccurrenceRequest;
use App\Http\Requests\SkipFixedExpenseOccurrenceRequest;
use App\Http\Requests\StoreFixedExpenseRequest;
use App\Http\Requests\UpdateFixedExpenseRequest;
use App\Http\Resources\FixedExpenseOccurrenceResource;
use App\Http\Resources\FixedExpenseResource;
use Illuminate\Http\JsonResponse;

class FixedExpenseController extends Controller
{
    public function __construct(
        protected FixedExpenses $fixedExpenses
    ) {
    }

    /**
     * Listar plantillas de gastos fijos paginadas.
     */
    public function index(ListFixedExpensesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 10);

        $paginator = $this->fixedExpenses->paginate($filters, $perPage);

        return ApiResponse::success(
            FixedExpenseResource::collection($paginator)->response()->getData(true),
            'ok'
        );
    }

    /**
     * Crear una nueva plantilla de gasto fijo.
     */
    public function store(StoreFixedExpenseRequest $request): JsonResponse
    {
        $fixedExpense = $this->fixedExpenses->create($request->validated());

        return ApiResponse::success(
            new FixedExpenseResource($fixedExpense),
            'Gasto fijo registrado con éxito',
            201
        );
    }

    /**
     * Actualizar una plantilla existente.
     */
    public function update(UpdateFixedExpenseRequest $request, int $id): JsonResponse
    {
        $fixedExpense = $this->fixedExpenses->update($id, $request->validated());

        return ApiResponse::success(
            new FixedExpenseResource($fixedExpense),
            'Gasto fijo actualizado con éxito'
        );
    }

    /**
     * Eliminar una plantilla de gasto fijo.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->fixedExpenses->delete($id);

        return ApiResponse::success(null, 'Gasto fijo eliminado con éxito');
    }

    /**
     * Activar o pausar una plantilla de gasto fijo.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $fixedExpense = $this->fixedExpenses->toggleActive($id);

        return ApiResponse::success(
            new FixedExpenseResource($fixedExpense),
            $fixedExpense->is_active ? 'Gasto fijo activado' : 'Gasto fijo pausado'
        );
    }

    /**
     * Listar cuotas (pendientes o historial) paginadas.
     */
    public function occurrences(ListFixedExpensesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $this->fixedExpenses->paginateOccurrences($filters, $perPage);

        return ApiResponse::success(
            FixedExpenseOccurrenceResource::collection($paginator)->response()->getData(true),
            'ok'
        );
    }

    /**
     * Resumen de cuotas pendientes agrupadas por moneda.
     */
    public function occurrencesSummary(): JsonResponse
    {
        $summary = $this->fixedExpenses->getOccurrencesSummary();

        return ApiResponse::success($summary, 'ok');
    }

    /**
     * Registrar el pago de una cuota de gasto fijo.
     */
    public function payOccurrence(PayFixedExpenseOccurrenceRequest $request, int $id): JsonResponse
    {
        $occurrence = $this->fixedExpenses->payOccurrence($id, $request->data);

        return ApiResponse::success(
            new FixedExpenseOccurrenceResource($occurrence),
            'Pago registrado con éxito'
        );
    }

    /**
     * Omitir una cuota por pagar.
     */
    public function skipOccurrence(SkipFixedExpenseOccurrenceRequest $request, int $id): JsonResponse
    {
        $occurrence = $this->fixedExpenses->skipOccurrence($id, $request->validated()['note'] ?? null);

        return ApiResponse::success(
            new FixedExpenseOccurrenceResource($occurrence),
            'Cuota omitida con éxito'
        );
    }

    /**
     * Disparar manualmente la generación de cuotas pendientes.
     */
    public function generateOccurrences(): JsonResponse
    {
        $generated = $this->fixedExpenses->generateDueOccurrences();

        return ApiResponse::success(
            ['generated_count' => $generated],
            "Se generaron {$generated} cuotas de gastos fijos"
        );
    }
}
