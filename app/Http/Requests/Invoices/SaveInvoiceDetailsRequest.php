<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class SaveInvoiceDetailsRequest extends FormRequest
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
        return [
            'invoice' => 'required|array',
            'invoice.supplier_discount_id' => 'nullable|exists:supplier_discounts,id',
            'details' => 'present|array',
            'details.*.product.id' => 'required|integer|exists:products,id',
            'details.*.quantity' => 'required|numeric|min:1',
            'details.*.unit_cost' => 'required|numeric|min:0',
            'details.*.lot_number' => 'required|string|max:100',
            'details.*.expiration_date' => 'required|date',
            'details.*.location' => 'nullable|string|max:100',
            'details.*.tax_enabled' => 'boolean',
            'details.*.is_return' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'invoice.required' => 'Faltan los datos de la cabecera de la factura.',
            'invoice.supplier_discount_id.exists' => 'El descuento seleccionado no es válido.',

            'details.present' => 'La lista de productos es obligatoria.',
            'details.array' => 'El formato de la lista de productos es incorrecto.',

            'details.*.product.id.exists' => 'Uno de los productos enviados no existe en la base de datos.',

            'details.*.quantity.required' => 'La cantidad es obligatoria para todos los productos.',
            'details.*.quantity.min' => 'La cantidad de los productos debe ser al menos 1.',

            'details.*.unit_cost.required' => 'El costo es obligatorio.',
            'details.*.unit_cost.min' => 'El costo no puede ser negativo.',

            'details.*.lot_number.required' => 'El N° de Lote es obligatorio para todos los productos.',
            'details.*.lot_number.max' => 'El N° de Lote es demasiado largo (máx 100 caracteres).',

            'details.*.expiration_date.required' => 'La Fecha de Vencimiento es obligatoria para todos los productos.',
            'details.*.expiration_date.date' => 'El formato de fecha de vencimiento es inválido.',

            'details.*.location.max' => 'La ubicación es demasiado larga.',
        ];
    }
}
