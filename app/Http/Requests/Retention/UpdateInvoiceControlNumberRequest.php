<?php

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceControlNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'number'           => 'sometimes|string|max:50|unique:retentions,number,' . $id,
            'date'             => 'sometimes|date',
            'invoices'         => 'sometimes|array',
            'invoices.*.id'    => 'required_with:invoices|integer|exists:invoices,id',
            'invoices.*.control_number' => 'required_with:invoices|nullable|string|max:50',
            'invoices.*.invoice_number' => 'required_with:invoices|nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'number.unique' => 'El número de comprobante ya está asignado a otra retención.',
        ];
    }
}
