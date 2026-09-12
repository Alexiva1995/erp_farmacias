<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductConsumptionRequest extends FormRequest
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
            'consumption_type' => ['nullable', 'string', 'in:chronic,single_treatment,no_alert,sporadic'],
            'treatment_duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
