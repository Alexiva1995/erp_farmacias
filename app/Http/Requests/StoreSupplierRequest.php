<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplierId = $this->route('supplier') ? $this->route('supplier')->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'social_reason' => ['string', 'max:255'],
            'sales_phone' => ['string', 'max:50', 'regex:/^\+?\d{7,15}$/'],
            'collections_phone' => ['string', 'max:50',  'regex:/^\+?\d{7,15}$/'],
            'credit_days' => ['numeric', 'min:0'],
            'dispatch_days' => ['array','min:1'],
            'dispatch_days.*' => [ 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'order_days' => ['array','min:1'],
            'order_days.*' => [ 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'payment_method' => ['in:Bs,Divisas'],
            'cash_payment' => ['boolean'],
            'charges_igtf' => ['boolean']
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
            'name.required' => 'El nombre del proveedor es obligatorio.',
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

            'order_days.min' => 'Debes seleccionar al menos un día de pedido.',
            'order_days.*.in' => 'Uno o más días seleccionados no son válidos.',

            'payment_method.*.in' => 'El método de pago seleccionado no es válido.',

            'cash_payment.boolean' => 'El valor para Pago de Contado no es válido.',
            'charges_igtf.boolean' => 'El valor para Cobra IGTF no es válido.',
        ];
    }
}