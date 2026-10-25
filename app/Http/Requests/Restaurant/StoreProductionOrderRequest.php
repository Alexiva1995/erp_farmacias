<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dish_id' => 'required|exists:dishes,id',
            'output_product_id' => 'nullable|exists:products,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'batch_multiplier' => 'nullable|numeric|min:0.01|max:100',
            'actual_quantity' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
