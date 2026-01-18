<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductIntoAutoOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "productId" => "required|exists:product_suppliers,id",
            "main_product_id" => "required|exists:products,id",
            "quantity" => [
                "required",
                "integer",
                "min:1",

                function ($attribute, $value, $fail) {
                    $exists = \DB::table("product_suppliers")
                        ->where("id", $this->productId)
                        ->where("quantity", ">=", $value)
                        ->exists();

                    if (!$exists) {
                        $fail("Cantidad no disponible.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            "productId.required" => "El producto del proveedor es obligatorio.",
            "main_product_id.required" => "Debe seleccionar un producto de la lista superior.",
            "quantity.required" => "Debe indicar la cantidad a solicitar",
            "quantity.integer" => "Debe indicar un dígito",
            "quantity.min" => "La cantidad mínima es 1",
        ];
    }
}
