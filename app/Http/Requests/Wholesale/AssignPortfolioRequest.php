<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class AssignPortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_ids'            => 'required|array|min:1',
            'client_ids.*'          => 'integer|exists:clients,id',
            'sales_rep_id'          => 'nullable|integer|exists:users,id',
            'telemarketing_user_id' => 'nullable|integer|exists:users,id',
            'collector_user_id'     => 'nullable|integer|exists:users,id',
            'delivery_route_id'     => 'nullable|integer|exists:delivery_routes,id',
        ];
    }
}
