<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteA2ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'     => 'nullable|integer|exists:a2_sync_tasks,id',
            'section'     => 'nullable|string',
            'suppliers'   => 'nullable|array',
            'payables'    => 'nullable|array',
            'clients'     => 'nullable|array',
            'receivables' => 'nullable|array',
            'products'    => 'nullable|array',
        ];
    }
}
