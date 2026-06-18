<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    if ($value === '00:00') {
                        return;
                    }
                    $start = $this->input('start_time');
                    if ($start && $value <= $start) {
                        $fail('La hora de fin debe ser posterior a la hora de inicio.');
                    }
                }
            ],
            'client_name' => 'required|string|max:255',
            'client_whatsapp' => 'required|string|regex:/^\+?[0-9]{8,15}$/',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'client_whatsapp.regex' => 'El número de WhatsApp debe tener un formato válido (entre 8 y 15 dígitos numéricos).',
        ];
    }
}
