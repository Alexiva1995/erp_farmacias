<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSentPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('force')) {
            $force = $this->input('force');
            if (is_bool($force)) {
                $val = $force;
            } elseif (is_string($force) || is_numeric($force)) {
                $val = filter_var($force, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } else {
                // Si viene un objeto o PointerEvent del frontend
                $val = false;
            }
            $this->merge([
                'force' => $val ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
        ];
    }
}
