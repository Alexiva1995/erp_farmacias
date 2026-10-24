<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDishRequest extends FormRequest
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
        $dishId = $this->route('dish') ?? $this->route('id');

        return [
            'name' => 'required|string|min:2|max:255|unique:dishes,name,' . $dishId,
            'category_id' => 'required|exists:categories,id',
            'percentage_profit' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'suggested_price' => 'required|numeric|min:0',
            'designated_price' => 'required|numeric|min:0',
            'status' => 'required|in:0,1,2,3',
            'iva' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'preparation_time_minutes' => 'nullable|integer|min:0',
            'kitchen_station_id' => 'nullable|integer',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => 'integer|exists:modifier_groups,id',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.product_id' => 'required|exists:products,id',
            'ingredients.*.portion' => 'required|numeric|min:0.0001',
            'ingredients.*.designated_cost' => 'required|numeric|min:0',
            'ingredients.*.is_removable' => 'nullable|boolean',
        ];
    }
}
