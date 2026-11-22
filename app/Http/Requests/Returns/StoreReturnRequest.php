<?php

declare(strict_types=1);

namespace App\Http\Requests\Returns;

use App\Enums\ReturnRefundMethodEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizar formato legado (order + product + returns_quantity) al formato unificado.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('order_id') && $this->has('order.id')) {
            $this->merge([
                'order_id' => $this->input('order.id'),
                'items' => [
                    [
                        'product_id' => $this->input('product.id'),
                        'quantity' => $this->input('returns_quantity'),
                    ],
                ],
                'refund_method' => $this->input('refund_method', ReturnRefundMethodEnum::BALANCE->value),
                'restock_inventory' => $this->input('restock_inventory', true),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'refund_method' => ['nullable', 'string', Rule::in(array_column(ReturnRefundMethodEnum::cases(), 'value'))],
            'restock_inventory' => 'nullable|boolean',
            'reason' => 'nullable|string|max:255',
            'approve_immediately' => 'nullable|boolean',
            'updated_lots' => 'nullable|array',
            'updated_lots.*.id' => 'required_with:updated_lots|integer|exists:product_lots,id',
            'updated_lots.*.quantity' => 'required_with:updated_lots|integer|min:0',
            'new_lots' => 'nullable|array',
            'new_lots.*.quantity' => 'required_with:new_lots|integer|min:1',
            'new_lots.*.lot_number' => 'nullable|string|max:100',
            'new_lots.*.expiration_date' => 'nullable|date',
            'new_lots.*.location' => 'nullable|string|max:100',
        ];
    }
}
