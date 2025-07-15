<?php

namespace App\Services\History;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class HistoryActionService
{
    /**
     * 
     *
     * @param array $data 
     * @param Product|null $product 
     * @return string|null 
     */
    private function handlePhotoUpload(array $data, ?Product $product = null): ?string
    {
        if (isset($data['photo_url']) && is_a($data['photo_url'], \Illuminate\Http\UploadedFile::class)) {
            if ($product && $product->photo_url) {
                Storage::disk('public')->delete($product->photo_url);
            }

            return $data['photo_url']->store('products', 'public');
        }

        return null;
    }

    /**
     *
     *
     * @param array $validatedData 
     * @return Product 
     */
    public function createProduct(array $validatedData): Product
    {
        $newPath = $this->handlePhotoUpload($validatedData);
        if ($newPath) {
            $validatedData['photo_url'] = $newPath;
        }

        $product = Product::create($validatedData);

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group']);

        return $product;
    }

    /**
     * 
     *
     * @param Product $product
     * @param array $validatedData
     * @return Product
     */
    public function updateProduct(Product $product, array $validatedData): Product
    {
        $newPath = $this->handlePhotoUpload($validatedData, $product);
        if ($newPath) {
            $validatedData['photo_url'] = $newPath;
        } else {
            unset($validatedData['photo_url']);
        }

        $product->update($validatedData);

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group']);

        return $product;
    }

    /**
     * Desasigna un producto de su grupo.
     *
     * @param Product $product
     * @return bool Devuelve true si el producto fue desasignado, false si no tenía grupo.
     */
    public function unassignFromGroup(Product $product): bool
    {
        if (is_null($product->group_id)) {
            return false;
        }

        $product->group_id = null;
        $product->save();

        return true;
    }

    /**
     * Elimina un producto.
     *
     * @param Product $product
     */
    public function deleteProduct(Product $product): void
    {
        if ($product->photo_url) {
            Storage::disk('public')->delete($product->photo_url);
        }
        $product->delete();
    }
}
