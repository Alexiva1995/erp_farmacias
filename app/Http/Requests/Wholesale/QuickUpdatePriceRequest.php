<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class QuickUpdatePriceRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para actualizar el precio del producto.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_usd' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
