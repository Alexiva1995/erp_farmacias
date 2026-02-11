<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateIncompleteProductRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id ?? null;

        return [
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode,' . $productId],
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'origin_id' => ['nullable', 'integer', 'exists:origins,id'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        // Si hay un error de barcode único, buscar el producto que ya tiene ese código
        if ($errors->has('barcode')) {
            $barcode = $this->input('barcode');
            $productId = $this->route('product')->id ?? null;

            if ($barcode) {
                $existingProduct = Product::where('barcode', $barcode)
                    ->where('id', '!=', $productId)
                    ->first();

                if ($existingProduct) {
                    $errors->forget('barcode');
                    $errors->add('barcode', "El código de barras está repetido. ID del producto que lo tiene: {$existingProduct->id}");
                }
            }
        }

        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validación',
                'errors' => $errors
            ], 422)
        );
    }
}
