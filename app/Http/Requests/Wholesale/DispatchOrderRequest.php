<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class DispatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_route_id' => 'nullable|integer|exists:delivery_routes,id',
        ];
    }
}
