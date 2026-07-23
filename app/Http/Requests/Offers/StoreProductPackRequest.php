<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'pack_config' => 'required|array',
            'total_price' => 'required|numeric|min:0',
            'max_quantity' => 'nullable|integer|min:1',
            'max_sale_date' => 'nullable|date|after_or_equal:today',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del pack es obligatorio.',
            'pack_config.required' => 'La configuración del pack es obligatoria.',
            'total_price.required' => 'El precio total es obligatorio.',
            'total_price.min' => 'El precio total no puede ser negativo.',
            'max_quantity.min' => 'La cantidad máxima debe ser al menos 1.',
            'max_sale_date.after_or_equal' => 'La fecha máxima de venta debe ser hoy o una fecha posterior.',
        ];
    }
}
