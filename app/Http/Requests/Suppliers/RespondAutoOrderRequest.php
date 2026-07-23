<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class RespondAutoOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                             => 'required|array',
            'items.*.id'                        => 'required|integer|exists:auto_order_details,id',
            'items.*.supplier_confirmed'        => 'required|boolean',
            'items.*.supplier_rejected_reason'  => 'nullable|string|max:255',
        ];
    }
}
