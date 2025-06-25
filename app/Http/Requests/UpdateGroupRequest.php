<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $groupId = $this->route('group')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups_products')->ignore($groupId),
            ],
        ];
    }
}
