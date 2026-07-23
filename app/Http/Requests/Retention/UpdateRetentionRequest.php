<?php

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'number' => 'required|string|max:50|unique:retentions,number,' . $id,
        ];
    }

    public function messages(): array
    {
        return [
            'number.unique' => 'El número de comprobante ingresado ya se encuentra asignado a otra retención.',
        ];
    }
}
