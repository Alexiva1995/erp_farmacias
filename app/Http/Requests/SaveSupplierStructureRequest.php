<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "start_row" => ["nullable", "integer", "min:1"],
            "cod_supplier" => ["nullable", "string"],
            "name" => ["nullable", "string"],
            "barcode_match" => ["nullable", "string"],
            "quantity" => ["nullable", "string"],
            "currency" => ["nullable", "numeric"],
            "unit_cost" => ["nullable", "string"],
            "unit_cost_usd" => ["nullable", "string"],
            "expiration" => ["nullable", "string"],
            "laboratory" => ["nullable", "string"],
            "active_ingredient" => ["nullable", "string"],
            "file_keyword" => ["nullable", "string"],
            "format_type" => ["nullable", "string", "in:primary,secondary,tertiary"],
        ];
    }
}
