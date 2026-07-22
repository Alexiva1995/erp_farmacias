<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:100|unique:invoices,invoice_number',
            'control_number' => 'required|string|max:100',
            'currency' => ['required', Rule::in(['Bs', 'USD', 'COP'])],
            'exp_date' => 'required|date',
            'payment_date' => 'nullable|date|after_or_equal:received_date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
            'created_invoice_date' => 'required|date',
        ];

        if ($this->input('currency') !== 'USD') {
            $rules['exchange_rate'] = 'required|numeric|gt:0';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        } else {
            $rules['exchange_rate'] = 'nullable|numeric';
            $rules['total_usd'] = 'nullable|numeric|gt:0';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'invoice_number.unique' => 'El número de factura ya ha sido registrado en el sistema.',
        ];
    }
}
