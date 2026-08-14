<?php

namespace App\Http\Requests\InventoryCycle;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSimple = filter_var($this->query('simple', false), FILTER_VALIDATE_BOOLEAN);
        $allowWithoutBarcode = $this->boolean('allow_without_barcode');

        $rules = [
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric',
            'discrepancy' => 'required|numeric',
        ];

        if (!$allowWithoutBarcode) {
            $rules['barcode'] = 'required|string';
        }

        if ($isSimple) {
            $rules['updated_lots'] = 'nullable|array';
            $rules['updated_lots.*.id'] = 'required_with:updated_lots|integer|exists:product_lots,id';
            $rules['updated_lots.*.quantity'] = 'required_with:updated_lots|numeric|min:0';
            $rules['new_lots'] = 'nullable|array';
            $rules['new_lots.*.lot_number'] = 'required_with:new_lots|string|max:255';
            $rules['new_lots.*.expiration_date'] = 'required_with:new_lots|date';
            $rules['new_lots.*.quantity'] = 'required_with:new_lots|numeric|min:0';
        }

        return $rules;
    }
}
