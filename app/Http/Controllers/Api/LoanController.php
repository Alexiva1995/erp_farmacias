<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\Loans\LoanActionService;
use App\Services\Loans\LoanQueryService;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanRequest;
use App\Http\Requests\StoreLoanPaymentRequest;
use App\Http\Resources\LoanResource;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        private LoanQueryService $loanQueryService,
        private LoanActionService $loanActionService
    ) {
    }

    /**
     * Lista los préstamos con filtros y paginación
     */
    public function index(Request $request)
    {
        $query = $this->loanQueryService->getFilteredQuery($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => LoanResource::collection($items),
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => LoanResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total()
        ]);
    }

    /**
     * Crea un nuevo préstamo
     */
    public function store(StoreLoanRequest $request)
    {
        try {
            $loan = $this->loanActionService->createLoan($request->validated());

            return response()->json([
                'message' => 'Préstamo creado con éxito.',
                'loan' => new LoanResource($loan)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el préstamo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un préstamo específico
     */
    public function show(Loan $loan)
    {
        // Cargamos la suma de pagos aprobados para evitar N+1 al devolver el detalle
        $loan->loadSum(['payments' => function ($q) {
            $q->where('status', \App\Models\Expense::STATUS_APPROVED);
        }], 'amount');

        return response()->json(['data' => new LoanResource($loan)]);
    }

    /**
     * Actualiza un préstamo existente
     */
    public function update(UpdateLoanRequest $request, Loan $loan)
    {
        try {
            $updatedLoan = $this->loanActionService->updateLoan(
                $loan,
                $request->validated()
            );

            return response()->json([
                'message' => 'Préstamo actualizado con éxito.',
                'loan' => new LoanResource($updatedLoan)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el préstamo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un préstamo
     */
    public function destroy(Loan $loan)
    {
        try {
            $this->loanActionService->deleteLoan($loan);

            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el préstamo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra un abono (pago) para un préstamo
     */
    public function addPayment(StoreLoanPaymentRequest $request, Loan $loan)
    {
        try {
            $expense = $this->loanActionService->registerPayment($loan, $request->validated());

            return response()->json([
                'message' => 'Abono registrado con éxito.',
                'expense' => $expense,
                'loan_remaining_balance' => $loan->fresh()->getRemainingBalance()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el abono: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el saldo total pendiente de todos los préstamos
     */
    public function getBalance(Request $request)
    {
        $loansBalance = $this->loanQueryService->calculateTotalBalance();

        return response()->json([
            'data' => [
                'total_balance' => $loansBalance,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString(),
                'description' => 'Saldo pendiente total de todos los préstamos'
            ],
            'message' => 'Saldo de préstamos calculado con éxito.'
        ], 200);
    }
}
