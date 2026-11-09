<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreKitchenWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza valores por defecto antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_type' => $this->input('item_type', 'product'),
            'reason' => $this->input('reason', 'burned_spoiled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', 'in:product,dish'],
            'product_id' => ['required_if:item_type,product', 'nullable', 'integer', 'exists:products,id'],
            'dish_id' => ['required_if:item_type,dish', 'nullable', 'integer', 'exists:dishes,id'],
            'lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
            'kitchen_station_id' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'in:expired,burned_spoiled,preparation_trim,staff_meal,courtesy,dropped'],
            'notes' => ['required', 'string', 'max:500'],
            'photo' => ['required', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required_if' => 'Debes indicar el ID del producto afectado.',
            'product_id.exists' => 'El ID de producto ingresado no existe en el inventario.',
            'dish_id.required_if' => 'Debes seleccionar el platillo afectado.',
            'quantity.required' => 'Debes indicar la cantidad perdida.',
            'quantity.min' => 'La cantidad perdida debe ser mayor a 0.',
            'notes.required' => 'La descripción de la pérdida es obligatoria.',
            'photo.required' => 'Debes anexar una fotografía de evidencia de la merma.',
            'photo.image' => 'El archivo adjunto debe ser una imagen válida (JPG, PNG, WEBP).',
            'photo.max' => 'La fotografía no debe superar los 5 MB.',
        ];
    }
}
