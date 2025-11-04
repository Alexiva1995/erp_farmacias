<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssociateProductToGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'productIds' => 'array',
            'productIds.*' => 'required|integer|exists:products,id'
        ];
    }

    public function messages(): array
    {
        return [
            'productIds.array' => 'Debe contener una lista de productos.',
            'productIds.*.required' => 'El producto es requerido.',
            'productIds.*.integer' => 'El id del producto debe ser un número.',
            'productIds.*.exists' => 'Algunos productos no se encuentran registrados.',
        ];
    }
}
