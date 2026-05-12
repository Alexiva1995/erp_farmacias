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
        
        // Asignar valores por defecto si no están presentes (el frontend no los envía)
        // unit_cost siempre debe tener un valor (0 por defecto) para evitar errores de BD
        if (!isset($validatedData['unit_cost']) || 
            $validatedData['unit_cost'] === null || 
            $validatedData['unit_cost'] === '') {
            $validatedData['unit_cost'] = 0;
        }
        
        // Asegurar que unit_cost sea numérico
        $validatedData['unit_cost'] = (float)($validatedData['unit_cost'] ?? 0);
        
        // sale_price: si no está presente, calcularlo o asignar 0
        if (!isset($validatedData['sale_price']) || 
            $validatedData['sale_price'] === null || 
            $validatedData['sale_price'] === '') {
            // Si unit_cost > 0, calcular el precio basado en rentabilidad
            if ($validatedData['unit_cost'] > 0) {
                $percentage = ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage;
                $validatedData['sale_price'] = $validatedData['unit_cost'] * (1 + ($percentage / 100));
            } else {
                $validatedData['sale_price'] = 0;
            }
        }
        
        // Asegurar que sale_price sea numérico
        $validatedData['sale_price'] = (float)($validatedData['sale_price'] ?? 0);
        
        if (!empty($validatedData['barcode'])) {
            $this->resolveBarcodeConflict($validatedData['barcode']);
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
        // Si sale_price viene como 0, no recalcularlo (para vendedores y supervisores)
        // De lo contrario, calcular el precio basado en rentabilidad solo si unit_cost está presente
        if ((!isset($validatedData['sale_price']) || $validatedData['sale_price'] != 0) && 
            isset($validatedData['unit_cost']) && 
            $validatedData['unit_cost'] !== null && 
            $validatedData['unit_cost'] > 0) {
            if ($product->profitability && $product->profitability->is_locked) {
                $percentage = $product->profitability->profitability_percentage;
            } else {
                $percentage = ProfitabilitySetting::orderBy('id', 'desc')->first()->default_profitability_percentage;
            }

            $validatedData['sale_price'] = $validatedData['unit_cost'] * (1 + ($percentage / 100));
        }
        
        if (!empty($validatedData['barcode'])) {
            $this->resolveBarcodeConflict($validatedData['barcode'], $product->id);
        }

        $product->update($validatedData);

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group']);

        return $product;
    }

    public function updateIncompleteFields(Product $product, array $data): bool
    {
        \DB::beginTransaction();
        try {
            $updateData = [];
            if (isset($data['barcode'])) {
                if (!empty($data['barcode'])) {
                    $this->resolveBarcodeConflict($data['barcode'], $product->id);
                }
                $updateData['barcode'] = $data['barcode'];
            }
            if (isset($data['laboratory_id'])) {
                $updateData['laboratory_id'] = $data['laboratory_id'];
            }
            if (isset($data['origin_id'])) {
                $updateData['origin_id'] = $data['origin_id'];
            }

            if (!empty($updateData)) {
                $product->update($updateData);
            }
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
     * Elimina lógicamente un producto.
     *
     * @param Product $product
     */
    public function deleteProduct(Product $product): void
    {
        $product->update(['is_deleted' => true]);
        $product->delete(); // SoftDeletes nativo de Laravel
    }

    /**
     * Restaura un producto eliminado lógicamente.
     *
     * @param int $productId
     * @return Product
     */
    public function restoreProduct(int $productId): Product
    {
        $product = Product::withTrashed()
            ->withoutGlobalScope('not_deleted')
            ->findOrFail($productId);

        $product->update(['is_deleted' => false]);
        $product->restore();

        return $product;
    }

    /**
     * Alterna el estado de producto escaso (is_scarce).
     *
     * @param Product $product
     * @return Product
     */
    public function toggleScarceProduct(\App\Models\Product $product)
    {
        $product->is_scarce = !$product->is_scarce;
        $product->save();

        // Limpiar caché del asistente de IA para forzar recálculo
        // Como las llaves son dinámicas (MD5), lo más seguro es usar un patrón 
        // o limpiar la caché si no se usan tags.
        // En este sistema, usaremos una aproximación segura para invalidar reportes de IA.
        \Illuminate\Support\Facades\Cache::flush(); // Opción drástica pero segura para este ERP

        return $product;
    }

    /**
     * Marca un producto para ser ignorado en las sugerencias por un tiempo determinado.
     *
     * @param Product $product
     * @param int $days
     * @return Product
     */
    public function ignoreProduct(Product $product, int $days = 7): Product
    {
        $product->ignore_until = now()->addDays($days);
        $product->save();
        return $product;
    }

    
    /**
     * Fusiona dos productos, actualizando todas las referencias del producto que se elimina
     * al producto que se mantiene.
     *
     * @param int $productId1
     * @param int $productId2
     * @param int $keepProductId El ID del producto que se mantiene
     * @return array
     * @throws \Exception
     */
    public function mergeProducts(int $productId1, int $productId2, int $keepProductId): array
    {
        // Verificar que el keepProductId sea uno de los dos productos
        if ($keepProductId !== $productId1 && $keepProductId !== $productId2) {
            throw new \Exception('El ID del producto a mantener debe ser uno de los dos productos proporcionados.');
        }

        // Determinar qué producto se mantiene y cuál se elimina
        $productToKeepId = $keepProductId;
        $productToDeleteId = ($keepProductId === $productId1) ? $productId2 : $productId1;

        // Verificar que ambos productos existen
        $productToKeep = Product::findOrFail($productToKeepId);
        $productToDelete = Product::findOrFail($productToDeleteId);

        DB::beginTransaction();
        try {
            // Actualizar todas las tablas que referencian product_id
            // Cambiar todas las referencias del producto que se elimina al producto que se mantiene
            // 1. product_lots
            DB::table('product_lots')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 2. product_suppliers
            DB::table('product_suppliers')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 3. expirations
            DB::table('expirations')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 4. individual_offers
            DB::table('individual_offers')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 5. returns (return_entries)
            DB::table('returns')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 6. quotation_products
            DB::table('quotation_products')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 7. product_profitability
            DB::table('product_profitability')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 8. product_counts
            DB::table('product_counts')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 9. order_details
            DB::table('order_details')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 10. inventory_movements
            DB::table('inventory_movements')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 11. invoice_details
            DB::table('invoice_details')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 12. psychotropic_controls
            DB::table('psychotropic_controls')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 13. invoices_counts
            DB::table('invoices_counts')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 14. sale_counts
            DB::table('sales_counts')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 15. employee_product (tabla pivot)
            DB::table('employee_product')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 16. auto_order_details
            DB::table('auto_order_details')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 17. product_failures
            DB::table('product_failures')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 18. product_distributions - No necesita actualización directa ya que no tiene product_id,
            // se actualiza automáticamente a través de product_counts que ya fue actualizado

            // 19. price_adjustment_logs
            DB::table('price_adjustment_logs')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 20. fiscal_history_details
            DB::table('fiscal_history_details')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 21. invoice_returns
            DB::table('invoice_returns')
                ->where('product_id', $productToDeleteId)
                ->update(['product_id' => $productToKeepId]);

            // 22. product_packs (actualizar pack_config JSON)
            $packs = DB::table('product_packs')
                ->whereNotNull('pack_config')
                ->get();

            foreach ($packs as $pack) {
                $packConfig = json_decode($pack->pack_config, true);
                if (is_array($packConfig) && isset($packConfig[$productToDeleteId])) {
                    // Si el producto que se elimina existe, moverlo al producto que se mantiene
                    if (isset($packConfig[$productToKeepId])) {
                        // Si ya existe el producto que se mantiene, combinar las cantidades/configuraciones
                        if (is_array($packConfig[$productToDeleteId]) && is_array($packConfig[$productToKeepId])) {
                            // Combinar configuraciones
                            $packConfig[$productToKeepId]['quantity'] = ($packConfig[$productToKeepId]['quantity'] ?? 0) + ($packConfig[$productToDeleteId]['quantity'] ?? 0);
                        } elseif (is_numeric($packConfig[$productToDeleteId]) && is_numeric($packConfig[$productToKeepId])) {
                            // Sumar cantidades simples
                            $packConfig[$productToKeepId] = $packConfig[$productToKeepId] + $packConfig[$productToDeleteId];
                        }
                    } else {
                        // Mover la configuración al producto que se mantiene
                        $packConfig[$productToKeepId] = $packConfig[$productToDeleteId];
                    }
                    unset($packConfig[$productToDeleteId]);
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
                if (is_array($products) && isset($products[$productToDeleteId])) {
                    // Si el producto que se elimina existe, moverlo al producto que se mantiene
                    if (isset($products[$productToKeepId])) {
                        // Si ya existe el producto que se mantiene, combinar las configuraciones
                        if (is_array($products[$productToDeleteId]) && is_array($products[$productToKeepId])) {
                            $products[$productToKeepId]['quantity'] = ($products[$productToKeepId]['quantity'] ?? 0) + ($products[$productToDeleteId]['quantity'] ?? 0);
                        }
                    } else {
                        // Mover la configuración al producto que se mantiene
                        $products[$productToKeepId] = $products[$productToDeleteId];
                    }
                    unset($products[$productToDeleteId]);
                    DB::table('prescription_offers')
                        ->where('id', $offer->id)
                        ->update(['products' => json_encode($products)]);
                }
            }

            // Eliminar lógicamente el producto que se fusiona
            $productToDelete->update(['is_deleted' => true]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Productos fusionados exitosamente. El producto ID {$productToDeleteId} ha sido fusionado con el producto ID {$productToKeepId}.",
                'merged_product_id' => $productToKeepId,
                'deleted_product_id' => $productToDeleteId
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al fusionar productos: ' . $e->getMessage());
            throw new \Exception('Error al fusionar productos: ' . $e->getMessage());
        }
    }

    /**
     * Resuelve conflictos de código de barras duplicado si el producto que lo posee está eliminado.
     */
    private function resolveBarcodeConflict(string $barcode, ?int $excludeProductId = null): void
    {
        $duplicateProduct = Product::withoutGlobalScope('not_deleted')
            ->withTrashed()
            ->where('barcode', $barcode)
            ->when($excludeProductId, function ($q) use ($excludeProductId) {
                $q->where('id', '!=', $excludeProductId);
            })
            ->first();

        if ($duplicateProduct) {
            if ($duplicateProduct->is_deleted || $duplicateProduct->trashed()) {
                $duplicateProduct->barcode = null;
                $duplicateProduct->save();
            }
        }
    }
}
