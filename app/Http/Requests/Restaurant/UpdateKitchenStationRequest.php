<?php

declare(strict_types=1);

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stationId = $this->route('kitchen_station')?->id ?? $this->route('kitchen_station');

        return [
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:30|unique:kitchen_stations,code,' . $stationId,
            'color' => 'nullable|string|max:30',
            'icon' => 'nullable|string|max:50',
            'printer_ip' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
