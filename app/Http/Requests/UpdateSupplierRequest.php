<?php

namespace App\Http\Requests;

use App\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Obtener el tipo de negocio de la base de datos
        $businessType = \DB::table('general_settings')->value('business_type') ?? 'pharmacy';
        $isRestaurant = $businessType === 'restaurant';

        if ($isRestaurant) {
            return [
                'type' => ['sometimes', 'string'],
                'name' => 'sometimes|string|max:255',
                'sales_phone' => 'sometimes|nullable|string|max:50',
                'social_reason' => 'sometimes|nullable|string|max:255',
                'rif' => 'sometimes|nullable|string|max:20',
                'address' => 'sometimes|nullable|string',
                'collections_phone' => 'sometimes|nullable|string|max:50',
                'payment_due_type' => 'sometimes|nullable|in:invoice_date,early_payment,custom',
                'custom_due_days' => 'nullable|integer|min:1',
                'payment_due_reference' => 'sometimes|nullable|in:receipt_date,issue_date',
                'invoice_date_reference' => 'nullable|in:receipt_date,expiration_date,issue_date',
            ];
        }

        return [
            'type' => ['sometimes', Rule::enum(SupplierType::class)],
            'name' => 'sometimes|string|max:255',
            'social_reason' => [Rule::requiredIf(fn() => $this->type === SupplierType::EXTERNO->value), 'sometimes', 'string', 'max:255'],
            'rif' => 'sometimes|string|max:20',
            'address' => [Rule::requiredIf(fn() => $this->type === SupplierType::EXTERNO->value), 'sometimes', 'nullable', 'string'],
            'sales_phone' => 'sometimes|nullable|string|max:50',
            'collections_phone' => 'sometimes|nullable|string|max:50',
            'credit_days' => 'sometimes|nullable|numeric',
            'is_indexed' => 'sometimes|boolean',
            'dispatch_days' => 'sometimes|nullable|array',
            'dispatch_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday',
            'order_days' => 'sometimes|nullable|array',
            'order_days.*' => 'nullable|array',
            'order_days.*.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday',
            'payment_method' => 'sometimes|nullable|in:Bs,Divisas',
            'cash_payment' => 'sometimes|boolean',
            'charges_igtf' => 'sometimes|boolean',
            //'supplier_payment_method' => 'required|string',
            //'supplier_payment_days' => 'sometimes|numeric|min:0',
            'payment_due_type' => 'sometimes|nullable|in:invoice_date,early_payment,custom',
            'custom_due_days' => 'nullable|integer|min:1',
            'payment_due_reference' => 'sometimes|nullable|in:receipt_date,issue_date',
            'invoice_date_reference' => 'sometimes|nullable|in:receipt_date,expiration_date,issue_date',
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
            'social_reason.required' => 'La razón social es obligatoria para proveedores externos.',

            'address.required' => 'La dirección es obligatoria para proveedores externos.',

            'sales_phone.string' => 'El teléfono de ventas debe ser texto.',
            'sales_phone.max' => 'El teléfono de ventas no puede exceder los 50 caracteres.',
            'sales_phone.regex' => 'El teléfono de ventas debe contener solo números.',

            'collections_phone.string' => 'El teléfono de cobranzas debe ser texto.',
            'collections_phone.max' => 'El teléfono de cobranzas no puede exceder los 50 caracteres.',
            'collections_phone.regex' => 'El teléfono de cobranzas debe contener solo números.',

            'credit_days.numeric' => 'Los días de crédito debe ser un número.',
            'credit_days.min' => 'Los días de crédito no puede ser negativo.',

            'dispatch_days.min' => 'Uno o más días seleccionados no son válidos.',
            'dispatch_days.*.in' => 'Uno o más días seleccionados no son válidos.',

            'order_days.*.array' => 'El formato de días de pedido es inválido.',
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
