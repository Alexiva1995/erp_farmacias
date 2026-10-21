<?php

declare(strict_types=1);

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class GenerateIslrRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'required|integer|exists:invoices,id',
            'concept_description' => 'required|string|max:255',
            'retention_percentage' => 'required|numeric|min:0.01|max:100',
            'subtrahend' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
            'starting_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Debe seleccionar al menos una factura para generar retención de ISLR.',
            'concept_description.required' => 'La descripción o concepto de la retención es obligatoria.',
            'retention_percentage.required' => 'El porcentaje de retención es obligatorio.',
        ];
    }
}
