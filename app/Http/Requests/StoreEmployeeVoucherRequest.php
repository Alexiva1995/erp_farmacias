<?php

namespace App\Http\Requests;

use DB;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user_id = $this->route('employee')->user->id;
        return [
            'name' => [
                'required',
                function ($attribute, $value, $fail) use ($user_id) {
                    $exists = DB::table('users_salary_details')
                        ->join('salary_concepts', 'users_salary_details.salary_concept_id', '=', 'salary_concepts.id')
                        ->where('users_salary_details.user_id', $user_id)
                        ->where('salary_concepts.name', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Este bono ya fue asignado a este empleado.');
                    }
                },
            ],
            'type' => 'required|in:deduction,salary',
            'frequency' => 'required|in:annual,monthly,fortnight',
            'amount' => 'required|decimal:0,2|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Debe seleccionar una opción.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'type.required' => 'El tipo de voucher es obligatorio.',
            'type.in' => 'El tipo debe ser deducción o salario.',
            'frequency.required' => 'La frecuencia es obligatoria.',
            'frequency.in' => 'La frecuencia debe ser anual, mensual o quincenal.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.decimal' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser al menos 1.',
        ];
    }
}
