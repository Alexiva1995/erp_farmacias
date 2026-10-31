<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class MergeLaboratoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Permitir enviar tanto un solo ID (source_laboratory_id) como múltiples IDs (source_laboratory_ids)
        if ($this->has('source_laboratory_id') && !$this->has('source_laboratory_ids')) {
            $this->merge([
                'source_laboratory_ids' => [(int) $this->input('source_laboratory_id')],
            ]);
        }

        if (is_array($this->input('source_laboratory_ids'))) {
            $cleanIds = array_values(array_unique(array_filter(
                array_map('intval', $this->input('source_laboratory_ids')),
                fn (int $id) => $id > 0
            )));
            $this->merge([
                'source_laboratory_ids' => $cleanIds,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_laboratory_id' => ['required', 'integer', 'min:1'],
            'source_laboratory_ids' => ['required', 'array', 'min:1'],
            'source_laboratory_ids.*' => ['required', 'integer', 'min:1', 'different:target_laboratory_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_laboratory_id.required' => 'Debe seleccionar el laboratorio principal (destino) que se conservará.',
            'source_laboratory_ids.required' => 'Debe seleccionar al menos un laboratorio duplicado para absorber.',
            'source_laboratory_ids.min' => 'Debe seleccionar al menos un laboratorio duplicado para absorber.',
            'source_laboratory_ids.*.different' => 'El laboratorio a absorber no puede ser el mismo que el laboratorio principal.',
        ];
    }
}
