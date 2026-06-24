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
                    $start = $this->input('start_time');
                    if ($start) {
                        // Si la hora de fin es menor que la de inicio, podría cruzar la medianoche.
                        // Permitimos esto solo si la hora de inicio es tarde (ej: después de las 18:00)
                        // y la hora de fin es de madrugada (ej: menor o igual a las 04:00)
                        $startHour = (int) explode(':', $start)[0];
                        $endHour = (int) explode(':', $value)[0];
                        
                        if ($endHour < $startHour) {
                            if ($startHour >= 18 && $endHour <= 4) {
                                return; // Cruce de medianoche válido
                            }
                            $fail('La hora de fin debe ser posterior a la hora de inicio.');
                        } else if ($value <= $start) {
                            $fail('La hora de fin debe ser posterior a la hora de inicio.');
                        }
                    }
                }
            ],
            'client_name' => 'required|string|max:255',
            'client_whatsapp' => 'required|string',
            'client_id' => 'nullable|integer|exists:clients,id',
            'identification' => 'required|string|max:20',
            'request_weekly_fixed' => 'nullable|boolean',
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
