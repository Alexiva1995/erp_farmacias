<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Loan;
use App\Contracts\Expenses;
use App\Models\ExpenseCategory;
use App\Data\CreateExpenseData;
use App\Models\Expense;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class LoanActionService
{
    public function __construct(
        protected Expenses $expensesService
    ) {}

    /**
     * Crea un nuevo préstamo
     * 
     * @param array $data
     * @return Loan
     */
    public function createLoan(array $data): Loan
    {
        $validated = $this->validateLoanData($data);

        return Loan::create($validated);
    }

    /**
     * Actualiza un préstamo existente
     * 
     * @param Loan $loan
     * @param array $data
     * @return Loan
     */
    public function updateLoan(Loan $loan, array $data): Loan
    {
        $validated = $this->validateLoanData($data);

        $loan->update($validated);

        return $loan->fresh();
    }

    /**
     * Elimina un préstamo
     * 
     * @param Loan $loan
     * @return bool
     * @throws \Exception
     */
    public function deleteLoan(Loan $loan): bool
    {
        try {
            return $loan->delete();
        } catch (\Exception $e) {
            throw new \Exception('No se pudo eliminar el préstamo: ' . $e->getMessage());
        }
    }

    /**
     * Busca un préstamo por ID
     * 
     * @param int $id
     * @return Loan
     * @throws ModelNotFoundException
     */
    public function findLoanById(int $id): Loan
    {
        return Loan::findOrFail($id);
    }

    /**
     * Valida los datos del préstamo
     * 
     * @param array $data
     * @return array
     * @throws \App\Exceptions\InvalidLoanException
     */
    private function validateLoanData(array $data): array
    {
        $validated = [
            'loan_date' => $data['loan_date'] ?? null,
            'monthly_payment' => isset($data['monthly_payment']) ? (float) $data['monthly_payment'] : 0.0,
            'total_installments' => isset($data['total_installments']) ? (int) $data['total_installments'] : 0,
        ];

        if (empty($validated['loan_date'])) {
            throw new \App\Exceptions\InvalidLoanException('La fecha del préstamo es requerida');
        }

        $date = \DateTime::createFromFormat('Y-m-d', $validated['loan_date']);
        if (!$date || $date->format('Y-m-d') !== $validated['loan_date']) {
            throw new \App\Exceptions\InvalidLoanException('La fecha del préstamo debe tener formato válido (Y-m-d)');
        }

        if ($validated['monthly_payment'] <= 0) {
            throw new \App\Exceptions\InvalidLoanException('La cuota mensual debe ser mayor a 0');
        }

        if ($validated['total_installments'] <= 0) {
            throw new \App\Exceptions\InvalidLoanException('El número total de cuotas debe ser mayor a 0');
        }

        if ($validated['total_installments'] > 600) {
            throw new \App\Exceptions\InvalidLoanException('El número total de cuotas no puede exceder 600 (50 años)');
        }

        return $validated;
    }

    /**
     * Registra un pago (abono) para un préstamo
     */
    public function registerPayment(Loan $loan, array $data): Expense
    {
        // 1. Buscar la categoría "Pagos de Préstamos"
        $category = ExpenseCategory::where('name', 'Pagos de Préstamos')->first();
        if (!$category) {
            $category = ExpenseCategory::create(['name' => 'Pagos de Préstamos']);
        }

        // 2. Preparar el objeto de datos para el gasto
        $expenseData = CreateExpenseData::from([
            'name' => "Abono Préstamo #{$loan->id}",
            'category_id' => $category->id,
            'amount' => (float) $data['amount'],
            'amount_usd' => (float) $data['amount'],
            'currency' => 'USD',
            'has_invoice' => false,
            'is_deductible' => false,
            'iva' => false,
            'expense_date' => new \DateTime($data['payment_date']),
            'user_id' => Auth::id() ?? 1, // Usuario actual o fallback al ID 1
            'account' => $data['account'], // El método de pago/cuenta
            'status' => 'Pending',
            'type_of_expense' => 'Normal',
            'total_amount' => (float) $data['amount'],
            'loan_id' => $loan->id,
        ]);

        // 3. Crear el gasto usando el servicio existente
        return $this->expensesService->create($expenseData);
    }
}
