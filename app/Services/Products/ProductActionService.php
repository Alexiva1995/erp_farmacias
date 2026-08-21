<?php

declare(strict_types=1);

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
        
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
        if ($isRestaurant) {
            if (!isset($validatedData['origin_id'])) {
                $validatedData['origin_id'] = null;
            }
            if (!isset($validatedData['active_ingredient'])) {
                $validatedData['active_ingredient'] = null;
            }
        }
        
        // Asegurar que unit_cost sea numérico
        $validatedData['unit_cost'] = (float)($validatedData['unit_cost'] ?? 0);
        
        // sale_price: si no está presente, calcularlo o asignar 0
        // sale_price: si no está presente, calcularlo o asignar 0
        if (!isset($validatedData['sale_price']) || 
            $validatedData['sale_price'] === null || 
            $validatedData['sale_price'] === '') {
            // Si unit_cost > 0, calcular el precio basado en rentabilidad
            if ($validatedData['unit_cost'] > 0) {
                $validatedData['sale_price'] = $this->calculateSalePrice((float) $validatedData['unit_cost']);
            } else {
                $validatedData['sale_price'] = 0;
            }
        }
        
        // Asegurar que sale_price sea numérico
        $validatedData['sale_price'] = (float)($validatedData['sale_price'] ?? 0);
        
        if (!empty($validatedData['barcode'])) {
            $this->resolveBarcodeConflict($validatedData['barcode']);
        }

        $supplierIds = $validatedData['supplier_ids'] ?? [];
        if (isset($validatedData['supplier_ids'])) {
            unset($validatedData['supplier_ids']);
        }

        if (isset($validatedData['supplier_id']) && $validatedData['supplier_id']) {
            $singleSupplierId = (int) $validatedData['supplier_id'];
            $supplierIds[] = $singleSupplierId;
            $supplierIds = array_unique($supplierIds);
        }
        // Eliminar supplier_id de los datos a guardar (no existe como columna directa)
        if (isset($validatedData['supplier_id'])) {
            unset($validatedData['supplier_id']);
        }

        $masterId = $validatedData['master_id'] ?? null;
        if (isset($validatedData['master_id'])) {
            unset($validatedData['master_id']);
        }

        if (!empty($masterId)) {
            $validatedData['id'] = (int) $masterId;
        } elseif (config('catalog.role') === 'slave') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterProduct = $masterClient->registerProductInMaster($validatedData);
                if (!empty($masterProduct['id'])) {
                    $validatedData['id'] = (int) $masterProduct['id'];
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo sincronizar ID con Master Catalog: ' . $e->getMessage());
            }
        }

        $product = Product::create($validatedData);

        if ($initialStock > 0) {
            $product->lots()->create([
                'lot_number'      => 'LOTE-INICIAL',
                'quantity'        => $initialStock,
                'unit_cost'       => $product->unit_cost ?? 0,
                'expiration_date' => now()->addYears(5)->format('Y-m-d'),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        if (!empty($supplierIds)) {
            foreach ($supplierIds as $supplierId) {
                $product->productSuppliers()->create([
                    'supplier_id' => $supplierId,
                    'connection_date' => now(),
                    'unit_cost' => $product->unit_cost ?? 0,
                ]);
            }
        }

        // Crear/Guardar las variantes
        if (request()->has('variants')) {
            $variantsInput = request()->input('variants');
            $variantsData = is_string($variantsInput) ? json_decode($variantsInput, true) : $variantsInput;
            $variantsData = is_array($variantsData) ? $variantsData : [];

            foreach ($variantsData as $v) {
                $product->variants()->create([
                    'attribute_type' => 'shade',
                    'attribute_value' => $v['attribute_value'] ?? '',
                    'color_hex' => $v['color_hex'] ?? '#E20074',
                    'price_modifier' => (float)($v['price_modifier'] ?? 0),
                    'stock' => (int)($v['stock'] ?? 0)
                ]);
            }
        }

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group', 'productSuppliers', 'variants']);

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

            $validatedData['sale_price'] = $this->calculateSalePrice((float) $validatedData['unit_cost'], $product);
        }
        
        if (!empty($validatedData['barcode'])) {
            $this->resolveBarcodeConflict($validatedData['barcode'], $product->id);
        }

        $supplierIds = $validatedData['supplier_ids'] ?? null;
        if (isset($validatedData['supplier_ids'])) {
            unset($validatedData['supplier_ids']);
        }

        if (isset($validatedData['supplier_id']) && $validatedData['supplier_id']) {
            $singleSupplierId = (int) $validatedData['supplier_id'];
            if ($supplierIds === null) {
                $supplierIds = [$singleSupplierId];
            } else {
                $supplierIds[] = $singleSupplierId;
                $supplierIds = array_unique($supplierIds);
            }
        }

        // Extraer variantes antes de actualizar
        $variantsData = request()->input('variants') ? json_decode(request()->input('variants'), true) : [];

        $product->update($validatedData);

        if ($supplierIds !== null) {
            $currentSupplierIds = $product->productSuppliers()->pluck('supplier_id')->toArray();
            
            // Eliminar los no seleccionados
            $toDelete = array_diff($currentSupplierIds, $supplierIds);
            if (!empty($toDelete)) {
                $product->productSuppliers()->whereIn('supplier_id', $toDelete)->delete();
            }
            
            // Crear los nuevos
            $toCreate = array_diff($supplierIds, $currentSupplierIds);
            foreach ($toCreate as $supplierId) {
                $product->productSuppliers()->create([
                    'supplier_id' => $supplierId,
                    'connection_date' => now(),
                    'unit_cost' => $product->unit_cost ?? 0,
                ]);
            }
        }

        // Sincronizar las variantes solo si están presentes en la request
        if (request()->has('variants')) {
            $variantsInput = request()->input('variants');
            $variantsData = is_string($variantsInput) ? json_decode($variantsInput, true) : $variantsInput;
            $variantsData = is_array($variantsData) ? $variantsData : [];

            $keepVariantIds = [];
            foreach ($variantsData as $v) {
                if (!empty($v['id'])) {
                    $variant = $product->variants()->find($v['id']);
                    if ($variant) {
                        $variant->update([
                            'attribute_value' => $v['attribute_value'] ?? '',
                            'color_hex' => $v['color_hex'] ?? '#E20074',
                            'price_modifier' => (float)($v['price_modifier'] ?? 0)
                        ]);
                        $keepVariantIds[] = $variant->id;
                    }
                } else {
                    $newVariant = $product->variants()->create([
                        'attribute_type' => 'shade',
                        'attribute_value' => $v['attribute_value'] ?? '',
                        'color_hex' => $v['color_hex'] ?? '#E20074',
                        'price_modifier' => (float)($v['price_modifier'] ?? 0),
                        'stock' => 0
                    ]);
                    $keepVariantIds[] = $newVariant->id;
                }
            }
            // Eliminar variantes viejas que ya no se pasaron en el payload
            $product->variants()->whereNotIn('id', $keepVariantIds)->delete();
        }

        $product->load(['category', 'laboratory', 'origin', 'lots', 'group', 'productSuppliers', 'variants']);

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
            $normalizedGroupId = ($groupId && $groupId > 0) ? $groupId : null;
            $product->update(['group_id' => $normalizedGroupId]);
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
     * Limpia el estado de ignorado para todos los productos, volviéndolos a mostrar en los reportes.
     *
     * @return int Cantidad de productos restaurados
     */
    public function clearIgnoredProducts(): int
    {
        return Product::whereNotNull('ignore_until')
            ->update(['ignore_until' => null]);
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

    /**
     * Calcula el precio de venta considerando la configuración global de rentabilidad (simple o compuesta).
     */
    private function calculateSalePrice(float $unitCost, ?Product $product = null): float
    {
        if ($unitCost <= 0) {
            return 0.0;
        }

        $generalSettings = DB::table('general_settings')->first();
        $useCompound = $generalSettings && isset($generalSettings->profitability_calculation_type) && $generalSettings->profitability_calculation_type === 'compound';

        $roundUsdUp = $generalSettings && !empty($generalSettings->round_usd_up);
        if ($useCompound) {
            $settings = ProfitabilitySetting::orderBy('id', 'desc')->first();
            $productProfitability = $product ? \App\Models\ProductProfitability::where('product_id', $product->id)->first() : null;

            $shippingCost = $productProfitability && $productProfitability->shipping_cost !== null ? (float)$productProfitability->shipping_cost : ($settings ? (float)$settings->shipping_cost : 0.0);
            $packagingCost = $productProfitability && $productProfitability->packaging_cost !== null ? (float)$productProfitability->packaging_cost : ($settings ? (float)$settings->packaging_cost : 0.0);
            $expenseMargin = $productProfitability && $productProfitability->expense_margin !== null ? (float)$productProfitability->expense_margin : ($settings ? (float)$settings->expense_margin : 0.0);
            $profitMargin = $productProfitability && $productProfitability->profit_margin !== null ? (float)$productProfitability->profit_margin : ($settings ? (float)$settings->profit_margin : 0.0);
            $taxUsa = $productProfitability && $productProfitability->tax_usa !== null ? (float)$productProfitability->tax_usa : ($settings ? (float)$settings->tax_usa : 0.0);

            $costWithTax = $unitCost * (1.0 + ($taxUsa / 100.0));
            $fixedExpenseAmount = $costWithTax * ($expenseMargin / 100.0);
            $profitDenominator = 1.0 - ($profitMargin / 100.0);
            if ($profitDenominator <= 0.0) {
                $profitDenominator = 0.01;
            }

            $calculatedPrice = ($costWithTax + $shippingCost + $packagingCost + $fixedExpenseAmount) / $profitDenominator;
            return $roundUsdUp ? (float) ceil(round($calculatedPrice, 4)) : round($calculatedPrice, 2);
        } else {
            if ($product && $product->profitability && $product->profitability->is_locked) {
                $percentage = (float) $product->profitability->profitability_percentage;
            } else {
                $setting = ProfitabilitySetting::orderBy('id', 'desc')->first();
                $percentage = $setting ? (float) $setting->default_profitability_percentage : 30.0;
            }

            $calculatedPrice = $unitCost * (1 + ($percentage / 100));
            return $roundUsdUp ? (float) ceil(round($calculatedPrice, 4)) : round($calculatedPrice, 2);
        }
    }
}

