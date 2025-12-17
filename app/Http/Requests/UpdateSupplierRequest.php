<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'social_reason' => 'sometimes|string|max:255',
            'sales_phone' => 'sometimes|string|max:50|regex:/^\+?\d{7,15}$/',
            'collections_phone' => 'sometimes|string|max:50|regex:/^\+?\d{7,15}$/',
            'credit_days' => 'sometimes|numeric|min:0',
            'dispatch_days' => 'sometimes|array|min:1',
            'dispatch_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday',
            'order_days' => 'required|array|min:1',
            'order_days.*' => 'array|min:1',
            'order_days.*.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday',
            'payment_method' => 'sometimes|in:Bs,Divisas',
            'cash_payment' => 'sometimes|boolean',
            'charges_igtf' => 'sometimes|boolean',
            //'supplier_payment_method' => 'required|string',
            //'supplier_payment_days' => 'sometimes|numeric|min:0',
            'payment_due_type' => 'sometimes|in:invoice_date,early_payment,custom',
            'custom_due_days' => 'nullable|integer|min:1',
            'payment_due_reference' => 'sometimes|in:receipt_date,issue_date',
            'invoice_date_reference' => 'required_if:payment_due_type,invoice_date|in:receipt_date,expiration_date,issue_date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.string' => 'El nombre del proveedor debe ser texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',

            'social_reason.string' => 'La razón social debe ser texto.',
            'social_reason.max' => 'La razón social no puede exceder los 255 caracteres.',

            'sales_phone.string' => 'El teléfono de ventas debe ser texto.',
            'sales_phone.max' => 'El teléfono de ventas no puede exceder los 50 caracteres.',
            'sales_phone.regex' => 'El teléfono de ventas debe contener solo números.',

            'collections_phone.string' => 'El teléfono de cobranzas debe ser texto.',
            'collections_phone.max' => 'El teléfono de cobranzas no puede exceder los 50 caracteres.',
            'collections_phone.regex' => 'El teléfono de cobranzas debe contener solo números.',

            'credit_days.numeric' => 'Los días de crédito debe ser un número.',
            'credit_days.min' => 'Los días de crédito no puede ser negativo.',

            'dispatch_days.min' => 'Debes seleccionar al menos un día de despacho.',
            'dispatch_days.*.in' => 'Uno o más días seleccionados no son válidos.',

            'order_days.required' => 'Debes asignar días de pedido.',
            'order_days.*.array' => 'El formato de días de pedido es inválido.',
            'order_days.*.min' => 'Cada día de despacho debe tener al menos un día de pedido.',
            'order_days.*.*.in' => 'Uno o más días seleccionados no son válidos.',

            'payment_method.*.in' => 'El método de pago seleccionado no es válido.',

            'cash_payment.boolean' => 'El valor para Pago de Contado no es válido.',
            'charges_igtf.boolean' => 'El valor para Cobra IGTF no es válido.',

            //'supplier_payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_due_type.in' => 'El tipo de vencimiento seleccionado no es válido.',
            'payment_due_type.required' => 'El tipo de vencimiento es obligatorio.',

            'custom_due_days.integer' => 'Los días de vencimiento personalizado deben ser un número entero.',
            'custom_due_days.min' => 'Los días de vencimiento personalizado deben ser al menos 1.',

            'payment_due_reference.in' => 'La referencia de vencimiento seleccionada no es válida.',
            'payment_due_reference.required' => 'La referencia de vencimiento es obligatoria.',

            'invoice_date_reference.in' => 'La referencia de fecha de factura seleccionada no es válida.',
            'invoice_date_reference.required_if' => 'La referencia de fecha de factura es obligatoria'
        ];
    }
}
