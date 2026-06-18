<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFixedScheduleRequest extends FormRequest
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
            'day_of_week' => 'required|integer|between:1,7',
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
}
