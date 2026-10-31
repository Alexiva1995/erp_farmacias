<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class CompletePickingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.order_detail_id' => 'required|integer|exists:order_details,id',
            'items.*.picked_quantity' => 'required|numeric|min:0',
        ];
    }
}
