<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; 

class UpdateOrderTotalsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper($this->currency),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', Rule::in(['USD', 'BS', 'COP'])],
            'total_amount_usd' => ['required', 'numeric', 'min:0'],
            'total_cost' => ['required', 'numeric', 'min:0'],
        ];
    }


     public function messages(): array
    {
        return [
            'total_amount.required' => 'El monto total de la orden es obligatorio',
            'currency.required' => 'El currency es obligatorio',
            'total_amount_usd.required' => 'El monto total de la orden es obligatorio',
            'total_cost.required' => 'El monto del costo de la orden es obligatorio',
        ];
    }
}
