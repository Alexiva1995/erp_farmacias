<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteSalesOrdersImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders'                           => ['required', 'array', 'min:1'],
            'orders.*.document_number'         => ['required', 'string'],
            'orders.*.order_date'              => ['nullable', 'string'],
            'orders.*.section_type'            => ['nullable', 'string'],
            'orders.*.client_ident'            => ['nullable', 'string'],
            'orders.*.client_name'             => ['nullable', 'string'],
            'orders.*.total_amount'            => ['required', 'numeric'],
            'orders.*.net_amount'              => ['nullable', 'numeric'],
            'orders.*.exempt_amount'           => ['nullable', 'numeric'],
            'orders.*.tax_amount'              => ['nullable', 'numeric'],
            'orders.*.items'                   => ['required', 'array', 'min:1'],
            'orders.*.items.*.barcode'         => ['required', 'string'],
            'orders.*.items.*.description'     => ['nullable', 'string'],
            'orders.*.items.*.quantity'        => ['required', 'numeric'],
            'orders.*.items.*.price'           => ['required', 'numeric'],
            'orders.*.items.*.total'           => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'orders.required'                     => 'Debe enviar al menos una orden de venta para procesar.',
            'orders.array'                        => 'La estructura de órdenes debe ser una lista.',
            'orders.*.document_number.required'   => 'El número de documento de la orden es obligatorio.',
            'orders.*.items.required'             => 'Cada orden debe contener al menos un producto.',
        ];
    }
}
