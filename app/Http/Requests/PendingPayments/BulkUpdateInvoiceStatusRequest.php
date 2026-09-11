<?php

declare(strict_types=1);

namespace App\Http\Requests\PendingPayments;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateInvoiceStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para actualización masiva de facturas.
     */
    public function rules(): array
    {
        return [
            'invoice_ids'       => ['nullable', 'array'],
            'invoice_ids.*'     => ['integer', 'exists:invoices,id'],
            'invoice_numbers'   => ['nullable', 'array'],
            'invoice_numbers.*' => ['string'],
        ];
    }

    /**
     * Validación personalizada para requerir al menos uno de los dos arrays.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = $this->input('invoice_ids', []);
            $numbers = $this->input('invoice_numbers', []);

            if (empty($ids) && empty($numbers)) {
                $validator->errors()->add('invoices', 'Debe proporcionar al menos un ID o número de factura.');
            }
        });
    }
}