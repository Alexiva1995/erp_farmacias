<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class A2StreamSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales'      => 'nullable|array',
            'payables'   => 'nullable|array',
            'clients'    => 'nullable|array',
            'checkpoint' => 'nullable|array',
        ];
    }
}
