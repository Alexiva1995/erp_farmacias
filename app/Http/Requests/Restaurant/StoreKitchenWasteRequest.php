<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreKitchenWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_type' => 'required|string|in:product,dish',
            'product_id' => 'required_if:item_type,product|nullable|exists:products,id',
            'dish_id' => 'required_if:item_type,dish|nullable|exists:dishes,id',
            'lot_id' => 'nullable|exists:product_lots,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|in:expired,burned_spoiled,preparation_trim,staff_meal,courtesy,dropped',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
