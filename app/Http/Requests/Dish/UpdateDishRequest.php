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
        $dishId = $this->route('dish');
        // Si el parámetro de ruta es un objeto Dish o el ID directamente
        $id = is_object($dishId) ? $dishId->id : $dishId;

        return [
            'name' => 'required|string|min:2|max:255|unique:dishes,name,' . $id,
            'category_id' => 'required|exists:categories,id',
            'percentage_profit' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'suggested_price' => 'required|numeric|min:0',
            'designated_price' => 'required|numeric|min:0',
            'status' => 'required|in:0,1,2,3',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.product_id' => 'required|exists:products,id',
            'ingredients.*.portion' => 'required|numeric|min:0.0001',
            'ingredients.*.designated_cost' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del plato es requerido.',
            'name.unique' => 'Ya existe un plato con este nombre.',
            'category_id.required' => 'La categoría es requerida.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'percentage_profit.required' => 'El porcentaje/multiplicador de ganancia es requerido.',
            'cost_price.required' => 'El precio de costo es requerido.',
            'suggested_price.required' => 'El precio sugerido es requerido.',
            'designated_price.required' => 'El precio designado es requerido.',
            'status.required' => 'El estado es requerido.',
            'ingredients.required' => 'Debes agregar al menos un ingrediente.',
            'ingredients.min' => 'Debes agregar al menos un ingrediente.',
        ];
    }
}
