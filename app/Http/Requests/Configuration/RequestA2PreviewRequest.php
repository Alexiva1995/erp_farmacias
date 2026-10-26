<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class RequestA2PreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => 'required|string|in:suppliers_payables,products_lots,operations,sales,all',
            'year'    => 'nullable|integer|min:2020|max:2030',
        ];
    }
}
