<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryBookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_cost_bs' => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'purchase_entries' => ['nullable', 'numeric', 'min:0'],
            'sales_exits' => ['nullable', 'numeric', 'min:0'],
            'withdrawals' => ['nullable', 'numeric', 'min:0'],
            'self_consumptions' => ['nullable', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'in:0,0.16'],
            'withdrawal_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
