<?php

namespace App\Services\Loans;

use App\Models\Loan;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoanActionService
{
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
     */
    private function validateLoanData(array $data): array
    {
        $validated = [
            'loan_date' => $data['loan_date'],
            'monthly_payment' => (float) $data['monthly_payment'],
            'total_installments' => (int) $data['total_installments'],
        ];

        if (empty($validated['loan_date'])) {
            throw new \InvalidArgumentException('La fecha del préstamo es requerida');
        }

        $date = \DateTime::createFromFormat('Y-m-d', $validated['loan_date']);
        if (!$date || $date->format('Y-m-d') !== $validated['loan_date']) {
            throw new \InvalidArgumentException('La fecha del préstamo debe tener formato válido (Y-m-d)');
        }

        if ($validated['monthly_payment'] <= 0) {
            throw new \InvalidArgumentException('La cuota mensual debe ser mayor a 0');
        }

        if ($validated['total_installments'] <= 0) {
            throw new \InvalidArgumentException('El número total de cuotas debe ser mayor a 0');
        }

        if ($validated['total_installments'] > 600) {
            throw new \InvalidArgumentException('El número total de cuotas no puede exceder 600 (50 años)');
        }

        return $validated;
    }
}
