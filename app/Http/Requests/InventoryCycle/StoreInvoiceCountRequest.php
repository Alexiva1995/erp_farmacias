<?php

namespace App\Http\Requests\InventoryCycle;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowWithoutBarcode = $this->boolean('allow_without_barcode');

        $rules = [
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric',
            'discrepancy' => 'required|numeric',
        ];

        if (!$allowWithoutBarcode) {
            $rules['barcode'] = 'required|string';
        }

        return $rules;
    }
}
