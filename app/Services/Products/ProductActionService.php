<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProfitabilitySetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $percentage = ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage;
        $validatedData['sale_price'] = $validatedData['unit_cost'] * (1 + ($percentage / 100));
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
        if ($product->profitability && $product->profitability->is_locked) {
            $percentage = $product->profitability->profitability_percentage;
        } else {
            $percentage = ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage;
        }

        $validatedData['sale_price'] = $validatedData['unit_cost'] * (1 + ($percentage / 100));
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

    public function updateProductGroup(Product $product, ?int $groupId): bool
    {
        \DB::beginTransaction();
        try {
            $product->update(['group_id' => $groupId]);
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

    /**
     * Fusiona dos productos, actualizando todas las referencias del producto con ID mayor
     * al producto con ID menor.
     *
     * @param int $productId1
     * @param int $productId2
     * @return array
     * @throws \Exception
     */
    public function mergeProducts(int $productId1, int $productId2): array
    {
        // Determinar ID menor y mayor
        $minId = min($productId1, $productId2);
        $maxId = max($productId1, $productId2);

        // Verificar que ambos productos existen
        $productMin = Product::findOrFail($minId);
        $productMax = Product::findOrFail($maxId);

        DB::beginTransaction();
        try {
            // Actualizar todas las tablas que referencian product_id
            // 1. product_lots
            DB::table('product_lots')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 2. product_suppliers
            DB::table('product_suppliers')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 3. expirations
            DB::table('expirations')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 4. individual_offers
            DB::table('individual_offers')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 5. returns (return_entries)
            DB::table('returns')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 6. quotation_products
            DB::table('quotation_products')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 7. product_profitability
            DB::table('product_profitability')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 8. product_counts
            DB::table('product_counts')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 9. order_details
            DB::table('order_details')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 10. inventory_movements
            DB::table('inventory_movements')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 11. invoice_details
            DB::table('invoice_details')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 12. psychotropic_controls
            DB::table('psychotropic_controls')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 13. invoices_counts
            DB::table('invoices_counts')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 14. sale_counts
            DB::table('sales_counts')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 15. employee_product (tabla pivot)
            DB::table('employee_product')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 16. auto_order_details
            DB::table('auto_order_details')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 17. product_failures
            DB::table('product_failures')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 18. product_distributions - No necesita actualización directa ya que no tiene product_id,
            // se actualiza automáticamente a través de product_counts que ya fue actualizado

            // 19. price_adjustment_logs
            DB::table('price_adjustment_logs')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 20. fiscal_history_details
            DB::table('fiscal_history_details')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 21. invoice_returns
            DB::table('invoice_returns')
                ->where('product_id', $maxId)
                ->update(['product_id' => $minId]);

            // 22. product_packs (actualizar pack_config JSON)
            $packs = DB::table('product_packs')
                ->whereNotNull('pack_config')
                ->get();

            foreach ($packs as $pack) {
                $packConfig = json_decode($pack->pack_config, true);
                if (is_array($packConfig) && isset($packConfig[$maxId])) {
                    // Si el producto con ID mayor existe, moverlo al ID menor
                    if (isset($packConfig[$minId])) {
                        // Si ya existe el ID menor, combinar las cantidades/configuraciones
                        if (is_array($packConfig[$maxId]) && is_array($packConfig[$minId])) {
                            // Combinar configuraciones
                            $packConfig[$minId]['quantity'] = ($packConfig[$minId]['quantity'] ?? 0) + ($packConfig[$maxId]['quantity'] ?? 0);
                        } elseif (is_numeric($packConfig[$maxId]) && is_numeric($packConfig[$minId])) {
                            // Sumar cantidades simples
                            $packConfig[$minId] = $packConfig[$minId] + $packConfig[$maxId];
                        }
                    } else {
                        // Mover la configuración al ID menor
                        $packConfig[$minId] = $packConfig[$maxId];
                    }
                    unset($packConfig[$maxId]);
                    DB::table('product_packs')
                        ->where('id', $pack->id)
                        ->update(['pack_config' => json_encode($packConfig)]);
                }
            }

            // 23. prescription_offers (actualizar products JSON)
            $prescriptionOffers = DB::table('prescription_offers')
                ->whereNotNull('products')
                ->get();

            foreach ($prescriptionOffers as $offer) {
                $products = json_decode($offer->products, true);
                if (is_array($products) && isset($products[$maxId])) {
                    // Si el producto con ID mayor existe, moverlo al ID menor
                    if (isset($products[$minId])) {
                        // Si ya existe el ID menor, combinar las configuraciones
                        if (is_array($products[$maxId]) && is_array($products[$minId])) {
                            $products[$minId]['quantity'] = ($products[$minId]['quantity'] ?? 0) + ($products[$maxId]['quantity'] ?? 0);
                        }
                    } else {
                        // Mover la configuración al ID menor
                        $products[$minId] = $products[$maxId];
                    }
                    unset($products[$maxId]);
                    DB::table('prescription_offers')
                        ->where('id', $offer->id)
                        ->update(['products' => json_encode($products)]);
                }
            }

            // Eliminar el producto con ID mayor
            if ($productMax->photo_url) {
                Storage::disk('public')->delete($productMax->photo_url);
            }
            $productMax->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => "Productos fusionados exitosamente. El producto ID {$maxId} ha sido fusionado con el producto ID {$minId}.",
                'merged_product_id' => $minId,
                'deleted_product_id' => $maxId
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al fusionar productos: ' . $e->getMessage());
            throw new \Exception('Error al fusionar productos: ' . $e->getMessage());
        }
    }
}
