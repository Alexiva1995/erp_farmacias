<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductFailureRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Obtiene los mensajes de validación.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'comment.max' => 'El comentario no debe exceder los 1000 caracteres.',
        ];
    }
}
