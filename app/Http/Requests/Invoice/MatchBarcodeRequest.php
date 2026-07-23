<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MatchBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => 'required|string',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'auto_order_id' => 'nullable|integer|exists:auto_orders,id',
        ];
    }
}
