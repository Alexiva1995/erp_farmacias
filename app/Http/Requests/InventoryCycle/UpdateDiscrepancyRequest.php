<?php

namespace App\Http\Requests\InventoryCycle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discrepancy' => 'required|numeric',
        ];
    }
}
