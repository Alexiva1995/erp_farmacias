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
     * Preparar datos antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $isFiscal = $this->has('is_fiscal') ? filter_var($this->input('is_fiscal'), FILTER_VALIDATE_BOOLEAN) : true;

        if (!$isFiscal) {
            $receivedDate = $this->input('received_date') ?: date('Y-m-d');
            $invoiceNumber = $this->input('invoice_number');
            $controlNumber = $this->input('control_number') ?: ($invoiceNumber ?: 'N/A');

            $this->merge([
                'is_fiscal' => false,
                'control_number' => $controlNumber,
                'created_invoice_date' => $this->input('created_invoice_date') ?: $receivedDate,
                'exp_date' => $this->input('exp_date') ?: $receivedDate,
                'payment_date' => $this->input('payment_date') ?: $receivedDate,
                'taxable_base' => $this->input('taxable_base', 0) ?: 0,
                'tax_amount' => $this->input('tax_amount', 0) ?: 0,
                'exempt_amount' => $this->input('exempt_amount') ?: $this->input('total_amount', 0),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $isFiscal = $this->has('is_fiscal') ? filter_var($this->input('is_fiscal'), FILTER_VALIDATE_BOOLEAN) : true;

        $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|max:100|unique:invoices,invoice_number',
            'control_number' => $isFiscal ? 'required|string|max:100' : 'nullable|string|max:100',
            'currency' => ['required', Rule::in(['Bs', 'USD', 'COP'])],
            'is_fiscal' => 'nullable|boolean',
            'is_expense' => 'nullable|boolean',
            'has_islr_retention' => 'nullable|boolean',
            'exp_date' => $isFiscal ? 'required|date' : 'nullable|date',
            'payment_date' => 'nullable|date',
            'received_date' => 'required|date',
            'discount_rule_id' => 'nullable|exists:discount_rules,id',
            'exempt_amount' => 'nullable|numeric|min:0',
            'taxable_base' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|gt:0',
            'created_invoice_date' => $isFiscal ? 'required|date' : 'nullable|date',
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
