<?php

declare(strict_types=1);

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventorySnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cutoff_date' => ['required', 'date', 'before_or_equal:today'],
            'period_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cutoff_date.required' => 'La fecha de corte es obligatoria.',
            'cutoff_date.date' => 'La fecha de corte debe tener un formato de fecha válido.',
            'cutoff_date.before_or_equal' => 'La fecha de corte no puede ser superior al día de hoy.',
            'period_days.min' => 'El periodo de ventas mínimo es de 7 días.',
            'period_days.max' => 'El periodo de ventas máximo es de 365 días.',
        ];
    }
}
