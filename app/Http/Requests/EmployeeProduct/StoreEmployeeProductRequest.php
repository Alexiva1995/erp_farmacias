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
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
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
            'product_ids.required' => 'Debe seleccionar al menos un producto',
            'product_ids.array' => 'Los productos deben ser un array',
            'product_ids.min' => 'Debe seleccionar al menos un producto',
            'product_ids.*.exists' => 'Uno o más productos seleccionados no existen',
        ];
    }
}
