<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class PrintFactoryInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip' => 'nullable|string|max:100',
            'port' => 'nullable|integer|min:1|max:65535',
        ];
    }
}
