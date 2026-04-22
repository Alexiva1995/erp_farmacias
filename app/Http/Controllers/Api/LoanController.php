<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\Loans\LoanActionService;
use App\Services\Loans\LoanQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

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
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }

    /**
     * Crea un nuevo préstamo
     */
    public function store(Request $request)
    {
        $rules = [
            'loan_date' => 'required|date|before_or_equal:today',
            'monthly_payment' => 'required|numeric|min:0.01',
            'total_installments' => 'required|integer|min:1|max:600',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $loan = $this->loanActionService->createLoan($validator->validated());

            return response()->json([
                'message' => 'Préstamo creado con éxito.',
                'loan' => $loan
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
        return response()->json(['data' => $loan]);
    }

    /**
     * Actualiza un préstamo existente
     */
    public function update(Request $request, Loan $loan)
    {
        $rules = [
            'loan_date' => 'required|date|before_or_equal:today',
            'monthly_payment' => 'required|numeric|min:0.01',
            'total_installments' => 'required|integer|min:1|max:600',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $updatedLoan = $this->loanActionService->updateLoan(
                $loan,
                $validator->validated()
            );

            return response()->json([
                'message' => 'Préstamo actualizado con éxito.',
                'loan' => $updatedLoan
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
    public function addPayment(Request $request, Loan $loan)
    {
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'account' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        try {
            $expense = $this->loanActionService->registerPayment($loan, $validator->validated());

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
