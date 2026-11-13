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
     * Normaliza valores por defecto y decodifica hojas de merma múltiples antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if (is_array($decoded)) {
                $this->merge(['items' => $decoded]);
            }
        }

        $this->merge([
            'item_type' => $this->input('item_type', 'product'),
            'reason' => $this->input('reason', 'burned_spoiled'),
        ]);
    }

    public function rules(): array
    {
        // Si se envía una Hoja de Merma en lote (múltiples renglones)
        if ($this->has('items') && is_array($this->input('items')) && count($this->input('items')) > 0) {
            return [
                'items' => ['required', 'array', 'min:1', 'max:50'],
                'items.*.item_type' => ['required', 'string', 'in:product,dish'],
                'items.*.product_id' => ['required_if:items.*.item_type,product', 'nullable', 'integer', 'exists:products,id'],
                'items.*.dish_id' => ['required_if:items.*.item_type,dish', 'nullable', 'integer', 'exists:dishes,id'],
                'items.*.lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
                'items.*.kitchen_station_id' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
                'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
                'items.*.reason' => ['required', 'string', 'in:expired,burned_spoiled,preparation_trim,staff_meal,courtesy,dropped'],
                'items.*.notes' => ['required', 'string', 'max:500'],
                'kitchen_station_id' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
                'photo' => ['nullable', 'image', 'max:5120'],
            ];
        }

        return [
            'item_type' => ['required', 'string', 'in:product,dish'],
            'product_id' => ['required_if:item_type,product', 'nullable', 'integer', 'exists:products,id'],
            'dish_id' => ['required_if:item_type,dish', 'nullable', 'integer', 'exists:dishes,id'],
            'lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
            'kitchen_station_id' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'reason' => ['required', 'string', 'in:expired,burned_spoiled,preparation_trim,staff_meal,courtesy,dropped'],
            'notes' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required_if' => 'Debes indicar el producto o insumo afectado.',
            'product_id.exists' => 'El ID de producto ingresado no existe en el inventario.',
            'dish_id.required_if' => 'Debes seleccionar el platillo o sub-receta afectada.',
            'dish_id.exists' => 'El platillo seleccionado no existe en el catálogo.',
            'quantity.required' => 'Debes indicar la cantidad perdida.',
            'quantity.min' => 'La cantidad perdida debe ser mayor a 0.',
            'notes.required' => 'La descripción de la pérdida es obligatoria.',
            'photo.required' => 'Debes anexar una fotografía de evidencia de la merma.',
            'photo.image' => 'El archivo adjunto debe ser una imagen válida (JPG, PNG, WEBP).',
            'photo.max' => 'La fotografía no debe superar los 5 MB.',
            'items.required' => 'Debes agregar al menos un renglón en la hoja de merma.',
        ];
    }
}
