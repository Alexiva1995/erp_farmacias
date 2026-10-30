<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientWholesaleTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_limit_usd'         => 'required|numeric|min:0',
            'base_credit_days'         => 'required|integer|min:0|max:180',
            'monthly_purchase_cap_usd' => 'nullable|numeric|min:0',
            'sacs_permit_number'       => 'nullable|string|max:100',
            'sicm_code'                => 'nullable|string|max:100',
            'linked_tenant_id'         => 'nullable|string|max:100',
            'b2b_portal_enabled'       => 'required|boolean',
        ];
    }
}
