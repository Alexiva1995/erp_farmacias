<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoOrderDetailsRequest extends FormRequest
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
            "details" => "required|array|min:1",
            "details.*.id" => "required|integer",
            "details.*.quantity" => "required|integer|min:1",
            "details.*.unit_cost" => "required|numeric|gt:0",
        ];
    }

    public function messages(): array
    {
        return [
            "details.required" => "La orden de compra debe tener productos.",
            "details.min" => "Debes enviar al menos un producto.",
            "details.*.id.required" => "El ID del producto es obligatorio.",
            "details.*.quantity.required" => "La cantidad es obligatoria.",
            "details.*.quantity.integer" => "La cantidad debe ser un número entero.",
            "details.*.quantity.min" => "La cantidad debe ser al menos 1.",
            "details.*.unit_cost.required" => "El costo es obligatorio.",
            "details.*.unit_cost.numeric" => "El costo debe ser un número.",
            "details.*.unit_cost.gt" => "El costo debe ser mayor que 0.",
        ];
    }
}
