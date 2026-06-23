<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessAuditRequest extends FormRequest
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
            'flow_id'                  => ['required', 'integer', 'exists:process_flows,id'],
            'order_id'                 => ['nullable', 'integer', 'exists:orders,id'],
            'cashier_id'               => ['nullable', 'integer', 'exists:employees,id'],
            'cook_id'                  => ['nullable', 'integer', 'exists:employees,id'],
            'phases'                   => ['required', 'array'],
            'phases.*.flow_phase_id'   => ['required', 'integer', 'exists:process_flow_phases,id'],
            'phases.*.seconds'         => ['required', 'integer', 'min:0'],
            'total_seconds'            => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'flow_id.required' => 'El flujo de proceso es obligatorio.',
            'flow_id.exists'   => 'El flujo de proceso seleccionado no es válido.',
            'order_id.exists'  => 'La orden seleccionada no es válida.',
            'cashier_id.exists'=> 'El cajero seleccionado no es válido.',
            'cook_id.exists'   => 'El cocinero seleccionado no es válido.',
            'phases.required'  => 'Los tiempos de fases son obligatorios.',
        ];
    }
}
