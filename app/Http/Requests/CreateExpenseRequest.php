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
            "name" => "required|string|max:255",
            "category_id" => "required|numeric|exists:expense_categories,id",
            "total_amount" => "required|numeric",
            "total_usd" => "required|numeric",
            "currency" => "required|string|max:10",
            "has_invoice" => "nullable|boolean:strict",
            "is_deductible" => "nullable|boolean:strict",
            "iva" => "nullable|boolean:strict",
            "expense_date" => "required|date",
            "exempt_amount" => "nullable|numeric|min:0",
            "taxable_base" => "nullable|numeric|min:0",
            "tax_amount" => "nullable|numeric|min:0",
            "exchange_rate" => "nullable|numeric|min:0",
            "user_id" => "required|numeric|exists:users,id",
            "count" => [
                "required",
                "string",
                Rule::in([
                    Expense::COUNT_EFECTIVO,
                    Expense::COUNT_TARJETA,
                    Expense::COUNT_PAGO_MOVIL,
                    Expense::COUNT_TRANSFERENCIA,
                    Expense::COUNT_BINANCE,
                    Expense::COUNT_PAYPAL,
                ]),
            ],
            'amount_bs' => ['nullable', 'numeric'],
            'conversion_rate' => ['nullable', 'numeric', 'min:0'],
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

            // Reglas para 'total_amount'
            'total_amount.required' => 'El total de la factura es obligatorio.',
            'total_amount.numeric' => 'El total de la factura debe ser un valor numérico.',

            // Reglas para 'total_usd'
            'total_usd.required' => 'El total en USD es obligatorio.',
            'total_usd.numeric' => 'El total en USD debe ser un valor numérico.',

            // Reglas para 'currency'
            'currency.required' => 'La moneda es obligatoria.',
            'currency.string' => 'La moneda debe ser una cadena de texto.',
            'currency.max' => 'La moneda no puede exceder los 10 caracteres.',

            // Reglas para 'has_invoice'
            'has_invoice.boolean' => 'El campo de factura debe ser verdadero o falso.',

            // Reglas para 'is_deductible'
            'is_deductible.boolean' => 'El campo deducible debe ser verdadero o falso.',

            // Reglas para 'iva'
            'iva.boolean' => 'El campo iva debe ser verdadero o falso.',

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

            // Reglas para 'amount_bs'
            'amount_bs.numeric' => 'El monto en BS debe ser un valor numérico.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Si es deducible y la moneda no es BS, se requiere la tasa de conversión
            if ($this->is_deductible === true && $this->currency !== 'BS') {
                if (empty($this->conversion_rate) || $this->conversion_rate <= 0) {
                    $validator->errors()->add(
                        'conversion_rate',
                        'La tasa de conversión a BS es obligatoria cuando el gasto es deducible y la moneda no es BS.'
                    );
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): JsonResponse
    {
        $errors = $validator->errors();
        $response = ApiResponse::error("Error", 422, $errors);
        throw new HttpResponseException($response);
    }

    protected function passedValidation()
    {
        // Si la moneda es BS, amount_bs debe ser igual a total_amount
        $amountBs = $this->amount_bs;
        if ($this->currency === 'BS') {
            $amountBs = $this->total_amount;
        }

        $this->data = CreateExpenseData::from([
            "name" => $this->name,
            "category_id" => $this->category_id,
            "amount" => $this->total_amount, // Mapear total_amount a amount
            "amount_usd" => $this->total_usd,    // Mapear total_usd a amount_usd
            "currency" => $this->currency,
            "has_invoice" => $this->has_invoice,
            "is_deductible" => $this->is_deductible,
            "iva" => $this->iva,
            "expense_date" => $this->expense_date,
            "user_id" => $this->user_id,
            "account" => $this->count,
            "type_of_expense" => Expense::TYPE_OF_EXPENSE_NORMAL,
            "amount_bs" => $amountBs,
            "conversion_rate" => $this->conversion_rate ?? null,
            "exempt_amount" => $this->exempt_amount ?? null,
            "taxable_base" => $this->taxable_base ?? null,
            "tax_amount" => $this->tax_amount ?? null,
            "exchange_rate" => $this->exchange_rate ?? null,
            "total_usd" => $this->total_usd ?? null,
            "total_amount" => $this->total_amount ?? null,
        ]);
    }
}
