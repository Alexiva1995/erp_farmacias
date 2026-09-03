<?php

declare(strict_types=1);

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class SupplierReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => 'nullable|string|max:100',
            'laboratory_id'  => 'nullable|integer|exists:laboratories,id',
            'supplier_id'    => 'nullable|integer|exists:suppliers,id',
        ];
    }
}
