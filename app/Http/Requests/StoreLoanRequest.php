<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'loan_date' => ['required', 'date', 'before_or_equal:today'],
            'monthly_payment' => ['required', 'numeric', 'min:0.01'],
            'total_installments' => ['required', 'integer', 'min:1', 'max:600'],
        ];
    }
}
