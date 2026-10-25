<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiningReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dining_table_id' => 'required|exists:dining_tables,id',
            'client_name' => 'required|string|max:100',
            'client_whatsapp' => 'nullable|string|max:50',
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'guests_count' => 'required|integer|min:1|max:30',
            'special_requests' => 'nullable|string|max:500',
            'deposit_amount_usd' => 'nullable|numeric|min:0',
        ];
    }
}
