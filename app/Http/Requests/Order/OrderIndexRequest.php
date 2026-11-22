<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el listado y resumen de órdenes del TPV.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:150'],
            'currency' => ['nullable', 'string', Rule::in(['BS', 'USD', 'COP'])],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', 'string', Rule::in(['Completed', 'Pending', 'Reserved', 'Cancelled', 'Abandoned', 'open', 'all'])],
            'status' => ['nullable', 'string', Rule::in(['Completed', 'Pending', 'Reserved', 'Cancelled', 'Abandoned', 'open', 'all'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortBy' => ['nullable', 'string', 'max:50'],
            'orderBy' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'has_fiscal' => ['nullable', 'string', Rule::in(['yes', 'pending'])],
            'only_psychotropic' => ['nullable', 'boolean'],
            'discount_type' => ['nullable', 'string', Rule::in(['company', 'doctor', 'recipe', 'individual'])],
            'dining_table_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Normaliza parámetros antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('only_psychotropic')) {
            $val = $this->input('only_psychotropic');
            if ($val === 'true' || $val === '1' || $val === 1) {
                $this->merge(['only_psychotropic' => true]);
            } elseif ($val === 'false' || $val === '0' || $val === 0 || $val === '') {
                $this->merge(['only_psychotropic' => false]);
            }
        }
    }
}
