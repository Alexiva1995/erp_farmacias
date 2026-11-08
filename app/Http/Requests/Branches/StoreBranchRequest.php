<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_code' => ['required', 'string', 'max:30'],
            'branch_id' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'company_name' => ['required', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:50'],
            'domain' => ['nullable', 'string', 'max:100'],
            'clone_catalog' => ['nullable', 'boolean'],
        ];
    }
}
