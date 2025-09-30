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
            "cod_supplier" => ["required", "string"],
            "name" => ["required", "string"],
            "barcode_match" => ["required", "string"],
            "quantity" => ["nullable", "required", "string"],
            "currency" => ["nullable", "required", "decimal:0,2"],
            "unit_cost" => ["nullable", "string"],
            "unit_cost_usd" => ["nullable", "string"],
            "expiration" => ["nullable", "required", "string"],
            "active_ingredient" => ["nullable", "string"],
            "file" => ["required", "file", "mimes:xlsx,xls"],
        ];
    }

    public function withValidator($validator)
    {
        $validator->sometimes('unit_cost', 'required', function ($input) {
            $currencyNotEmpty = !empty($input->currency);
            $usdNotEmpty = !empty($input->unit_cost_usd);
            return !($currencyNotEmpty && $usdNotEmpty);
        });
    }

    public function messages(): array
    {
        return [
            "start_row.required" => "La fila de inicio es obligatoria.",
            "start_row.min" => "La fila de inicio debe ser al menos 1.",
            "cod_supplier.required" => "La columna de código es obligatoria.",
            "name.required" => "La columna de nombre es obligatoria.",
            "barcode_match.required" => "La columna de código de barras es obligatoria.",
            "currency.decimal" => "La columna de tasa de cambio debe ser un número.",
            "quantity.integer" => "La columna de cantidad debe ser un número entero.",
            "quantity.min" => "La cantidad debe ser mayor o igual a 0.",
            "unit_cost.required" => "La columna de coste unitario es obligatoria.",
            "unit_cost.numeric" => "El coste unitario debe ser un número.",
            "unit_cost.min" => "El coste unitario debe ser mayor o igual a 0.",
            "unit_cost_usd.numeric" => "El coste unitario USD debe ser un número.",
            "unit_cost_usd.min" => "El coste unitario USD debe ser mayor o igual a 0.",
            "expiration.date_format" => "La fecha de expiración debe estar en el formato día/mes/año.",
            "file.required" => "Debes seleccionar un archivo.",
            "file.file" => "Debes seleccionar un archivo.",
            "file.mimes" => "El archivo debe ser de tipo Excel (.xlsx o .xls).",
            "active_ingredient.required" => 'Debe indicar el valor de la columna',
            "active_ingredient.string" => 'La columna debe ser un texto'
        ];
    }
}
