<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiscalCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Limpiar y normalizar los datos de entrada antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payload') && is_array($this->payload)) {
            $payload = $this->payload;
            if (isset($payload['client_rif'])) {
                $payload['client_rif'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $payload['client_rif']));
            }
            if (isset($payload['machine_serial'])) {
                $payload['machine_serial'] = strtoupper(trim((string) $payload['machine_serial']));
            }
            $this->merge(['payload' => $payload]);
        }
    }

    public function rules(): array
    {
        return [
            'command'                    => 'required|string|in:REPORT_Z,REPORT_X,REPRINT_INVOICE,CREDIT_NOTE,REPRINT_REPORT_Z',
            'payload'                    => 'nullable|array',
            'payload.invoice_number'     => 'required_if:command,REPRINT_INVOICE,CREDIT_NOTE|string',
            'payload.z_number'           => 'required_if:command,REPRINT_REPORT_Z|numeric',
            'payload.machine_serial'     => 'required_if:command,CREDIT_NOTE|string|max:20',
            'payload.invoice_date'       => 'required_if:command,CREDIT_NOTE|string|max:10',
            'payload.invoice_hour'       => 'required_if:command,CREDIT_NOTE|string|max:8',
            'payload.refund_amount'      => 'required_if:command,CREDIT_NOTE|numeric|min:0.01',
            'payload.client_name'        => 'nullable|string|max:38',
            'payload.client_rif'         => 'nullable|string|max:12',
            'payload.is_taxable'         => 'nullable|boolean',
        ];
    }
}
