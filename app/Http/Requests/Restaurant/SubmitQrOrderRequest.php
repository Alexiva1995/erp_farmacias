<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQrOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests_count' => 'nullable|integer|min:1|max:50',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|exists:dishes,id',
            'items.*.quantity' => 'required|numeric|min:0.5',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.seat_number' => 'nullable|integer',
            'items.*.course_number' => 'nullable|integer',
        ];
    }
}
