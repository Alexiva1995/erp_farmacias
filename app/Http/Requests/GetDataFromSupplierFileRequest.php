<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetDataFromSupplierFileRequest extends FormRequest
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
            "start_row" => ["required", "integer", "min:1"],
            "cod_supplier" => ["nullable", "string"],
            "name" => ["required", "string"],
            "barcode_match" => ["nullable", "string"],
            "quantity" => ["nullable", "string"],
            "currency" => ["nullable"],
            "unit_cost" => ["nullable", "string"],
            "unit_cost_usd" => ["nullable", "string"],
            "expiration" => ["nullable", "string"],
            "credit_days" => ["nullable", "string"],
            "laboratory" => ["nullable", "string"],
            "active_ingredient" => ["nullable", "string"],
            "file_keyword" => ["nullable", "string"],
            "file" => ["required", "file", "mimes:xlsx,xls,csv"],
            "format_type" => ["nullable", "string", "in:primary,secondary,tertiary"],
            "save_as_secondary" => ["nullable"],
            "save_as_tertiary" => ["nullable"],
        ];
    }

    public function withValidator($validator)
    {
        $validator->sometimes('unit_cost_usd', 'required', function ($input) {
            return empty($input->unit_cost_usd) && empty($input->unit_cost);
        });
    }

    public function messages(): array
    {
        return [
            "start_row.required" => "La fila de inicio es obligatoria.",
            "start_row.min" => "La fila de inicio debe ser al menos 1.",
            "name.required" => "La columna de nombre es obligatoria.",
            "barcode_match.required" => "La columna de código de barras es obligatoria.",
            "quantity.integer" => "La columna de cantidad debe ser un número entero.",
            "quantity.min" => "La cantidad debe ser mayor o igual a 0.",
            "unit_cost_usd.required" => "Debe indicar la columna para Costo 1.",
            "unit_cost.required" => "Debe indicar la columna de costo.",
            "file.required" => "Debes seleccionar un archivo.",
            "file.file" => "Debes seleccionar un archivo.",
            "file.mimes" => "El archivo debe ser de tipo Excel (.xlsx o .xls).",
            "active_ingredient.required" => 'Debe indicar el valor de la columna',
            "active_ingredient.string" => 'La columna debe ser un texto'
        ];
    }
}
