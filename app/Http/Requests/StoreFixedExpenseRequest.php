<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FixedExpenseIntervalUnit;
use App\Helpers\ApiResponse;
use App\Models\Expense;
use App\Services\FixedExpenseService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreFixedExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', Rule::in(['USD', 'BS', 'COP'])],
            'is_amount_variable' => ['nullable', 'boolean'],
            'default_count' => [
                'nullable',
                'string',
                Rule::in([
                    Expense::COUNT_EFECTIVO,
                    Expense::COUNT_TARJETA,
                    Expense::COUNT_PAGO_MOVIL,
                    Expense::COUNT_TRANSFERENCIA,
                    Expense::COUNT_BINANCE,
                    Expense::COUNT_PAYPAL,
                    Expense::COUNT_CAMBISTA,
                ]),
            ],
            'interval_unit' => ['required', Rule::enum(FixedExpenseIntervalUnit::class)],
            'interval_count' => ['required', 'integer', 'min:1', 'max:365'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:' . FixedExpenseService::MAX_REMINDER_DAYS],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del gasto fijo es obligatorio.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.in' => 'La moneda seleccionada no es válida.',
            'default_count.in' => 'El método de pago seleccionado no es válido.',
            'interval_unit.required' => 'La frecuencia es obligatoria.',
            'interval_unit.enum' => 'La frecuencia seleccionada no es válida.',
            'interval_count.required' => 'Indique cada cuánto se repite el gasto.',
            'interval_count.min' => 'El intervalo debe ser al menos 1.',
            'interval_count.max' => 'El intervalo no puede superar 365.',
            'start_date.required' => 'La fecha del primer pago es obligatoria.',
            'start_date.date' => 'La fecha del primer pago no es válida.',
            'end_date.date' => 'La fecha de finalización no es válida.',
            'end_date.after_or_equal' => 'La fecha de finalización debe ser posterior al primer pago.',
            'reminder_days_before.required' => 'Indique con cuántos días de anticipación avisar.',
            'reminder_days_before.min' => 'Los días de aviso no pueden ser negativos.',
            'reminder_days_before.max' => 'Los días de aviso no pueden superar ' . FixedExpenseService::MAX_REMINDER_DAYS . '.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error('Error', 422, $validator->errors()));
    }
}
