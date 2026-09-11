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
            "quantity" => [
                "required",
                "integer",
                "min:1",

                function ($attribute, $value, $fail) {
                    $productSupplier = \DB::table("product_suppliers")
                        ->where("id", $this->productId)
                        ->first(['quantity']);

                    if (!$productSupplier) {
                        return;
                    }

                    // Solo validar disponibilidad si el proveedor especifica una cantidad en inventario mayor a 0
                    if ($productSupplier->quantity !== null && (int)$productSupplier->quantity > 0) {
                        if ((int)$value > (int)$productSupplier->quantity) {
                            $fail("Cantidad no disponible. El proveedor solo dispone de {$productSupplier->quantity} unidades.");
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            "productId.required" => "El producto del proveedor es obligatorio.",
            "quantity.required" => "Debe indicar la cantidad a solicitar",
            "quantity.integer" => "Debe indicar un dígito",
            "quantity.min" => "La cantidad mínima es 1",
        ];
    }
}
