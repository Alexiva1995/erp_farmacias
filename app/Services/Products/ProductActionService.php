<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProfitabilitySetting;
use Illuminate\Support\Facades\Storage;

class ProductActionService
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
        $validatedData['sale_price'] = $validatedData['unit_cost'] + ((ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage / 100) * $validatedData['unit_cost']);
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
        $validatedData['sale_price'] = $validatedData['unit_cost'] + ((ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage / 100) * $validatedData['unit_cost']);
        $product->update($validatedData);

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group']);

        return $product;
    }

    public function updateProductBarcode(Product $product, int $barcode): bool
    {
        \DB::beginTransaction();
        try {
            $product->update(['barcode' => $barcode]);
            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \Log::error($e);
            \DB::rollback();
            return false;
        }
    }

    public function updateProductLaboratory(Product $product, ?int $laboratoryId): bool
    {
        \DB::beginTransaction();
        try {
            $product->update(['laboratory_id' => $laboratoryId]);
            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \Log::error($e);
            \DB::rollback();
            return false;
        }
    }

    public function updateProductOrigin(Product $product, ?int $originId): bool
    {
        \DB::beginTransaction();
        try {
            $product->update(['origin_id' => $originId]);
            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \Log::error($e);
            \DB::rollback();
            return false;
        }
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
