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
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($productId) {
                    if (empty($value)) {
                        return;
                    }

                    // Buscar producto duplicado (incluyendo los no eliminados y los eliminados)
                    $existingProduct = Product::withoutGlobalScope('not_deleted')
                        ->withTrashed()
                        ->with('laboratory')
                        ->where('barcode', $value)
                        ->where('id', '!=', $productId)
                        ->first();

                    if ($existingProduct) {
                        // Si el producto existente está eliminado, permitimos la validación para que el servicio lo fusione automáticamente
                        if ($existingProduct->is_deleted || $existingProduct->trashed()) {
                            return;
                        }

                        // Si está activo, informamos ID, nombre y laboratorio
                        $labName = $existingProduct->laboratory?->name ?? 'Sin Laboratorio';
                        $fail("El código de barras '{$value}' ya está asignado al producto ID #{$existingProduct->id} - {$existingProduct->name} (Lab: {$labName}).");
                    }
                },
            ],
            'laboratory_id' => ['nullable', 'integer', 'exists:laboratories,id'],
            'origin_id' => ['nullable', 'integer', 'exists:origins,id'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
