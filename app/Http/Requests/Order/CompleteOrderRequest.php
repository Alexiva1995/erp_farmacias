<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation (decode JSON strings).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('items')) {
            $items = $this->items;
            if (is_string($items)) {
                $decoded = json_decode($items, true);
                $merge['items'] = is_array($decoded) ? $decoded : [];
            }
        }

        if ($this->has('payments')) {
            $payments = $this->payments;
            if (is_string($payments)) {
                $decoded = json_decode($payments, true);
                $merge['payments'] = is_array($decoded) ? $decoded : [];
            }
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $orderId = $this->route('orderId');
        $orderId = is_object($orderId) ? $orderId->id : $orderId;

        return [
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.currency' => ['nullable', 'string', Rule::in(['USD', 'BS', 'COP'])],

            'items' => ['nullable', 'array'],
            'items.*.order_detail_id' => [
                'required',
                'integer',
                Rule::exists('order_details', 'id')->where('order_id', $orderId),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.price_before_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_type' => ['nullable', 'string'],
            'items.*.discount_source_id' => ['nullable'],

            'client_id' => [
                Rule::requiredIf(fn () => filter_var($this->credit, FILTER_VALIDATE_BOOLEAN)),
                'nullable',
                'exists:clients,id',
            ],
            'changeAmount' => ['nullable', 'numeric', 'min:0'],
            'changeAmountUSD' => ['nullable', 'numeric', 'min:0'],
            'taxable_base' => ['nullable', 'numeric', 'min:0'],
            'spe_surcharge_rate' => ['nullable', 'numeric', 'min:0'],
            'spe_surcharge_amount' => ['nullable', 'numeric', 'min:0'],

            'prescription_image' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required' => 'Los métodos de pago son obligatorios.',
            'payments.min' => 'Debe incluir al menos un método de pago.',
            'payments.*.method.required' => 'Cada pago debe tener un método definido.',
            'payments.*.amount.required' => 'Cada pago debe tener un monto.',
            'payments.*.amount.min' => 'El monto del pago no puede ser negativo.',
            'items.*.order_detail_id.exists' => 'Uno o más ítems no pertenecen a esta orden.',
        ];
    }
}
