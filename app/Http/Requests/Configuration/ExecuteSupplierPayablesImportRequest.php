<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteSupplierPayablesImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers'                          => ['required', 'array', 'min:1'],
            'suppliers.*.name'                   => ['required', 'string'],
            'suppliers.*.rif'                    => ['nullable', 'string'],
            'suppliers.*.sales_phone'            => ['nullable', 'string'],
            'suppliers.*.address'                => ['nullable', 'string'],
            'suppliers.*.type'                   => ['required', 'string', 'in:drogueria,externo'],
            'suppliers.*.is_new'                 => ['required', 'boolean'],
            'suppliers.*.existing_id'            => ['nullable', 'integer'],
            'suppliers.*.update_data'            => ['nullable', 'array'],
            'suppliers.*.invoices'               => ['nullable', 'array'],
            'suppliers.*.invoices.*.invoice_number'     => ['required', 'string'],
            'suppliers.*.invoices.*.created_date'       => ['nullable', 'string'],
            'suppliers.*.invoices.*.exp_date'           => ['nullable', 'string'],
            'suppliers.*.invoices.*.total_amount'       => ['nullable', 'numeric'],
            'suppliers.*.invoices.*.net_payable_amount' => ['nullable', 'numeric'],
            'suppliers.*.invoices.*.total_usd'          => ['nullable', 'numeric'],
            'suppliers.*.invoices.*.exchange_rate'      => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'suppliers.required'        => 'Debe enviar al menos un proveedor a procesar.',
            'suppliers.array'           => 'La estructura de proveedores debe ser una lista.',
            'suppliers.*.name.required' => 'El nombre del proveedor es obligatorio.',
            'suppliers.*.type.in'       => 'El tipo de proveedor debe ser "drogueria" o "externo".',
        ];
    }
}
