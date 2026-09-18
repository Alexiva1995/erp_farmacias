<?php

declare(strict_types=1);

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class TransferBetweenWalletsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Reglas de validación para la transferencia entre cajas.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_currency' => 'required|string|in:USD,BS,COP',
            'source_type' => 'required|string',
            'source_amount' => 'required|numeric|min:0.01',
            'destination_currency' => 'required|string|in:USD,BS,COP',
            'destination_type' => 'required|string',
            'destination_amount' => 'required|numeric|min:0.01',
            'exchange_rate' => 'nullable|numeric|min:0.000001',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
