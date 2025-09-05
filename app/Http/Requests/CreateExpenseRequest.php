<?php

namespace App\Http\Requests;

use App\Data\CreateExpenseData;
use App\Helpers\ApiResponse;
use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CreateExpenseRequest extends FormRequest
{

    public CreateExpenseData $data;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "name"                   =>    "required|string|max:255",
            "category_id"            =>    "required|numeric|exists:expense_categories,id",
            "amount"                 =>    "required|numeric",
            "amount_usd"             =>    "required|numeric",
            "currency"               =>    "required|string|max:10",
            "has_invoice"            =>    "nullable|boolean:strict",
            "is_deductible"          =>    "nullable|boolean:strict",
            "expense_date"           =>    "required|date",
            "user_id"                =>    "required|numeric|exists:users,id",
            "count"                  =>    [
                "required",
                "string",
                Rule::in([
                    Expense::COUNT_EFECTIVO,
                    Expense::COUNT_TARJETA,
                    Expense::COUNT_PAGO_MOVIL,
                    Expense::COUNT_TRANSFERENCIA,
                    Expense::COUNT_BINANCE,
                    Expense::COUNT_PAYPAL,
                ])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Reglas para 'name'
            'name.required' => 'El nombre del gasto es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',

            // Reglas para 'category_id'
            'category_id.required' => 'La categoría del gasto es obligatoria.',
            'category_id.numeric' => 'La categoría debe ser un valor numérico.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',

            // Reglas para 'amount'
            'amount.required' => 'El monto del gasto es obligatorio.',
            'amount.numeric' => 'El monto debe debe ser un valor numérico.',

            // Reglas para 'amount_usd'
            'amount_usd.required' => 'El monto en USD es obligatorio.',
            'amount_usd.numeric' => 'El monto en USD debe ser un valor numérico.',

            // Reglas para 'currency'
            'currency.required' => 'La moneda es obligatoria.',
            'currency.string' => 'La moneda debe ser una cadena de texto.',
            'currency.max' => 'La moneda no puede exceder los 10 caracteres.',

            // Reglas para 'has_invoice'
            'has_invoice.boolean' => 'El campo de factura debe ser verdadero o falso.',

            // Reglas para 'is_deductible'
            'is_deductible.boolean' => 'El campo deducible debe ser verdadero o falso.',

            // Reglas para 'expense_date'
            'expense_date.required' => 'La fecha del gasto es obligatoria.',
            'expense_date.date' => 'La fecha debe ser una fecha válida.',

            // Reglas para 'user_id'
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.numeric' => 'El usuario debe ser un valor numérico.',
            'user_id.exists' => 'El usuario seleccionado no es válido.',

            // Reglas para 'count'
            'count.required' => 'El método de pago es obligatorio.',
            'count.string' => 'El método de pago debe ser una cadena de texto.',
            'count.in' => 'El método de pago seleccionado no es válido.',
        ];
    }


    protected function failedValidation(Validator $validator): JsonResponse
    {
        $errors = $validator->errors();
        $response = ApiResponse::error("Error", 422, $errors);
        throw new HttpResponseException($response);
    }

    protected function passedValidation()
    {
        $this->data = CreateExpenseData::from([
            "name"                    =>    $this->name,
            "category_id"             =>    $this->category_id,
            "amount"                  =>    $this->amount,
            "amount_usd"              =>    $this->amount_usd,
            "currency"                =>    $this->currency,
            "has_invoice"             =>    $this->has_invoice,
            "is_deductible"           =>    $this->is_deductible,
            "expense_date"            =>    $this->expense_date,
            "user_id"                 =>    $this->user_id,
            "count"                   =>    $this->count,
        ]);
    }
}
