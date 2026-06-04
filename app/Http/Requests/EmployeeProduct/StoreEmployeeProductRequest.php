<?php

namespace App\Http\Requests\EmployeeProduct;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeProductRequest extends FormRequest
{
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
            'employee_id' => 'required|exists:employees,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'dish_ids' => 'nullable|array',
            'dish_ids.*' => 'exists:dishes,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Debe seleccionar un empleado',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'product_ids.array' => 'Los productos deben ser un array',
            'product_ids.*.exists' => 'Uno o más productos seleccionados no existen',
            'dish_ids.array' => 'Los platos deben ser un array',
            'dish_ids.*.exists' => 'Uno o más platos seleccionados no existen',
        ];
    }
}
