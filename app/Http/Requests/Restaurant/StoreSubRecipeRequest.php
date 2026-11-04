<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'category_id' => 'nullable',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'expected_yield_quantity' => 'required|numeric|min:0.01',
            'unit_of_measure' => 'nullable|string|max:50',
            'shelf_life_days' => 'nullable|integer|min:1',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.product_id' => 'required|exists:products,id',
            'ingredients.*.portion' => 'required|numeric|min:0.0001',
            'ingredients.*.designated_cost' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sub-receta es requerido.',
            'expected_yield_quantity.required' => 'Debes indicar cuántas unidades o rendimiento rinde la receta.',
            'expected_yield_quantity.min' => 'El rendimiento debe ser mayor a 0.',
            'ingredients.required' => 'Debes agregar al menos un insumo/ingrediente.',
            'ingredients.min' => 'Debes agregar al menos un insumo/ingrediente.',
        ];
    }
}
