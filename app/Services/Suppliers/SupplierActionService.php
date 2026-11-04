<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\PaymentRule;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierDiscount;
use App\Models\SupplierLaboratory;
use Illuminate\Support\Facades\DB;

class SupplierActionService
{
    public function __construct(
        private SupplierRepositoryInterface $supplierRepository
    ) {}

    /**
     * Crea un nuevo proveedor.
     *
     * @param array $validatedData 
     * @return Supplier 
     */
        public function createSupplier(array $validatedData): Supplier
    {
        $type = $validatedData['type'] ?? 'drogueria';
        $typeValue = $type instanceof \App\Enums\SupplierType ? $type->value : (string) $type;

        // Solo droguerías / mercancía farmacéutica se registran en el Catálogo Maestro central (tovaerp_master)
        // para unificar y obtener el ID oficial sin propagarse a los demás tenants.
        // Proveedores de gastos ('externo') son locales del tenant.
        if ($typeValue !== 'externo') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterSupplier = $masterClient->registerSupplierInMaster($validatedData);
                if (!empty($masterSupplier['id'])) {
                    $masterId = (int) $masterSupplier['id'];
                    if (!Supplier::where('id', $masterId)->exists()) {
                        $validatedData['id'] = $masterId;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo sincronizar proveedor con Master Catalog: ' . $e->getMessage());
            }
        } else {
            unset($validatedData['id']);
        }

        // Asegurar campos json obligatorios en esquema de proveedores
        if (!isset($validatedData['dispatch_days']) || $validatedData['dispatch_days'] === null) {
            $validatedData['dispatch_days'] = [];
        }
        if (!isset($validatedData['order_days']) || $validatedData['order_days'] === null) {
            $validatedData['order_days'] = [];
        }

        return $this->supplierRepository->create($validatedData);
    }
    public function updateSupplier(Supplier $supplier, array $validatedData): Supplier
    {
        return $this->supplierRepository->update($supplier, $validatedData);
    }

    /**
     * Elimina un proveedor.
     *
     * @param Supplier $supplier
     */
    public function deleteSupplier(Supplier $supplier): void
    {
        $this->supplierRepository->delete($supplier);
    }

    /**
     * Actualiza o crea una regla de pago para un proveedor.
     *
     * @param Supplier $supplier
     * @param array $data
     * @return PaymentRule
     */
    public function createPaymentRule(Supplier $supplier, array $data): PaymentRule
    {
        if (isset($data['id']) && $data['id'] > 0) {
            return $supplier->paymentRules()->updateOrCreate(
                ['id' => $data['id']],
                [
                    'days' => $data['days'],
                    'discount_percentage' => $data['discount_percentage'],
                    'supplier_id' => $supplier->id,
                ]
            );
        }

        return $supplier->paymentRules()->create([
            'days' => $data['days'],
            'discount_percentage' => $data['discount_percentage'],
            'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * Crea un enlace de laboratorio para un proveedor.
     *
     * @param Supplier $supplier
     * @param array $validatedData
     * @return SupplierLaboratory
     */
    public function attachLaboratory(Supplier $supplier, array $validatedData): SupplierLaboratory
    {
        $values = [
            'phone' => $validatedData['phone'],
            'laboratory_id' => $validatedData['laboratory_id'],
            'supplier_id' => $supplier->id, 
        ];
        $isUpdate = isset($validatedData['id']) && $validatedData['id'] > 0;
        if ($isUpdate) {
            $link = $supplier->laboratoryLinks()->findOrFail($validatedData['id']);
            $link->update($values);
            return $link;
        } else {
            return $supplier->laboratoryLinks()->create($values);
        }
    }

    public function createDiscount(Supplier $supplier, array $data): SupplierDiscount
    {
        return $supplier->discounts()->create($data);
    }

    public function applyGlobalDiscount(Supplier $supplier, float $percentage)
    {
        $factor = max(0, 1 - ($percentage / 100));

        if ($percentage <= 0) {
            return DB::table('product_suppliers')
                ->where('supplier_id', $supplier->id)
                ->update([
                    'unit_cost_with_discount' => DB::raw('unit_cost'),
                    'unit_cost_usd_with_discount' => DB::raw('unit_cost_usd'),
                    'updated_at' => now(),
                ]);
        }

        return DB::table('product_suppliers')
            ->where('supplier_id', $supplier->id)
            ->update([
                'unit_cost_with_discount' => DB::raw("ROUND(unit_cost * {$factor}, 2)"),
                'unit_cost_usd_with_discount' => DB::raw("ROUND(unit_cost_usd * {$factor}, 2)"),
                'updated_at' => now(),
            ]);
    }

    public function deleteProductsOlderThan(string $date)
    {
        return ProductSupplier::where(function ($q) use ($date) {
                $q->whereDate('updated_at', '<', $date)
                  ->orWhereDate('created_at', '<', $date);
            })
            ->whereDoesntHave('autoOrderDetails')
            ->delete();
    }

    /**
     * Alterna o establece el estado activo/desactivado de un producto de proveedor.
     *
     * @param ProductSupplier $productSupplier
     * @param bool|null $status
     * @return ProductSupplier
     */
    public function toggleProductSupplierStatus(ProductSupplier $productSupplier, ?bool $status = null): ProductSupplier
    {
        $newStatus = $status !== null ? $status : !$productSupplier->is_active;
        $productSupplier->update([
            'is_active' => $newStatus,
        ]);

        return $productSupplier;
    }

    /**
     * Alterna o establece el estado activo/desactivado de un proveedor.
     *
     * @param Supplier $supplier
     * @param bool|null $status
     * @return Supplier
     */
    public function toggleSupplierStatus(Supplier $supplier, ?bool $status = null): Supplier
    {
        $newStatus = $status !== null ? $status : !($supplier->is_active ?? true);
        $supplier->update([
            'is_active' => $newStatus,
        ]);

        if (!$newStatus) {
            ProductSupplier::where('supplier_id', $supplier->id)->delete();
        }

        return $supplier;
    }

    /**
     * Fusiona dos proveedores: el proveedor destino (target) absorbe toda la información y relaciones
     * del proveedor origen (source), y este último es eliminado.
     *
     * @param int $targetSupplierId
     * @param int $sourceSupplierId
     * @return Supplier
     */
    public function mergeSuppliers(int $targetSupplierId, int $sourceSupplierId): Supplier
    {
        return DB::transaction(function () use ($targetSupplierId, $sourceSupplierId) {
            $target = Supplier::findOrFail($targetSupplierId);
            $source = Supplier::findOrFail($sourceSupplierId);

            // 1. Facturas y Retenciones
            if (\Illuminate\Support\Facades\Schema::hasTable('invoices') && \Illuminate\Support\Facades\Schema::hasColumn('invoices', 'supplier_id')) {
                DB::table('invoices')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('retentions') && \Illuminate\Support\Facades\Schema::hasColumn('retentions', 'supplier_id')) {
                DB::table('retentions')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 2. Órdenes automáticas y reposición
            if (\Illuminate\Support\Facades\Schema::hasTable('auto_orders') && \Illuminate\Support\Facades\Schema::hasColumn('auto_orders', 'supplier_id')) {
                DB::table('auto_orders')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('auto_replenishment_configs') && \Illuminate\Support\Facades\Schema::hasColumn('auto_replenishment_configs', 'supplier_id')) {
                DB::table('auto_replenishment_configs')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 3. Movimientos de inventario, lotes, vencimientos y psicotrópicos
            if (\Illuminate\Support\Facades\Schema::hasTable('inventory_movements') && \Illuminate\Support\Facades\Schema::hasColumn('inventory_movements', 'supplier_id')) {
                DB::table('inventory_movements')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('product_lots') && \Illuminate\Support\Facades\Schema::hasColumn('product_lots', 'supplier_id')) {
                DB::table('product_lots')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('expirations') && \Illuminate\Support\Facades\Schema::hasColumn('expirations', 'supplier_id')) {
                DB::table('expirations')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('psychotropic_controls') && \Illuminate\Support\Facades\Schema::hasColumn('psychotropic_controls', 'supplier_id')) {
                DB::table('psychotropic_controls')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 4. Productos asociados (solo si la tabla posee la columna supplier_id)
            if (\Illuminate\Support\Facades\Schema::hasTable('products') && \Illuminate\Support\Facades\Schema::hasColumn('products', 'supplier_id')) {
                DB::table('products')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 5. Ofertas de proveedor (product_suppliers)
            if (\Illuminate\Support\Facades\Schema::hasTable('product_suppliers') && \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'supplier_id')) {
                $targetPsMap = DB::table('product_suppliers')
                    ->where('supplier_id', $target->id)
                    ->whereNotNull('product_id')
                    ->pluck('id', 'product_id')
                    ->toArray();

                $targetProductIds = array_keys($targetPsMap);

                if (!empty($targetProductIds)) {
                    $sourcePsRows = DB::table('product_suppliers')
                        ->where('supplier_id', $source->id)
                        ->whereIn('product_id', $targetProductIds)
                        ->get(['id', 'product_id']);

                    $sourcePsToDelete = $sourcePsRows->pluck('id')->toArray();

                    if (!empty($sourcePsToDelete)) {
                        // Reasignar detalles de órdenes automáticas antes de eliminar duplicados
                        if (\Illuminate\Support\Facades\Schema::hasTable('auto_order_details') && \Illuminate\Support\Facades\Schema::hasColumn('auto_order_details', 'product_suppliers_id')) {
                            foreach ($sourcePsRows as $srcPs) {
                                $targetPsId = $targetPsMap[$srcPs->product_id] ?? null;
                                if ($targetPsId) {
                                    DB::table('auto_order_details')
                                        ->where('product_suppliers_id', $srcPs->id)
                                        ->update(['product_suppliers_id' => $targetPsId]);
                                }
                            }
                        }

                        // Eliminar rechazos de IA asociados a los que se borrarán
                        if (\Illuminate\Support\Facades\Schema::hasTable('supplier_ai_match_rejections') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_ai_match_rejections', 'product_supplier_id')) {
                            DB::table('supplier_ai_match_rejections')->whereIn('product_supplier_id', $sourcePsToDelete)->delete();
                        }
                    }

                    DB::table('product_suppliers')
                        ->where('supplier_id', $source->id)
                        ->whereIn('product_id', $targetProductIds)
                        ->delete();
                }
                DB::table('product_suppliers')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 6. Laboratorios vinculados (evitar duplicados)
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_laboratories') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_laboratories', 'supplier_id')) {
                $targetLabMap = DB::table('supplier_laboratories')
                    ->where('supplier_id', $target->id)
                    ->pluck('id', 'laboratory_id')
                    ->toArray();

                $existingLabIds = array_keys($targetLabMap);

                if (!empty($existingLabIds)) {
                    $sourceLabRows = DB::table('supplier_laboratories')
                        ->where('supplier_id', $source->id)
                        ->whereIn('laboratory_id', $existingLabIds)
                        ->get(['id', 'laboratory_id']);

                    if ($sourceLabRows->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('discount_rules') && \Illuminate\Support\Facades\Schema::hasColumn('discount_rules', 'supplier_laboratory_id')) {
                        foreach ($sourceLabRows as $srcLab) {
                            $targetSlId = $targetLabMap[$srcLab->laboratory_id] ?? null;
                            if ($targetSlId) {
                                DB::table('discount_rules')
                                    ->where('supplier_laboratory_id', $srcLab->id)
                                    ->update(['supplier_laboratory_id' => $targetSlId]);
                            }
                        }
                    }

                    DB::table('supplier_laboratories')
                        ->where('supplier_id', $source->id)
                        ->whereIn('laboratory_id', $existingLabIds)
                        ->delete();
                }
                DB::table('supplier_laboratories')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 7. Reglas de pago y descuentos
            if (\Illuminate\Support\Facades\Schema::hasTable('payment_rules') && \Illuminate\Support\Facades\Schema::hasColumn('payment_rules', 'supplier_id')) {
                DB::table('payment_rules')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_discounts') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_discounts', 'supplier_id')) {
                DB::table('supplier_discounts')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('suppliers_config_products') && \Illuminate\Support\Facades\Schema::hasColumn('suppliers_config_products', 'supplier_id')) {
                DB::table('suppliers_config_products')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 8. Métodos de pago (evitar duplicados con unique ['supplier_id', 'type'])
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_payment_methods') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_payment_methods', 'supplier_id')) {
                $existingPaymentTypes = DB::table('supplier_payment_methods')
                    ->where('supplier_id', $target->id)
                    ->pluck('type')
                    ->toArray();
                if (!empty($existingPaymentTypes)) {
                    DB::table('supplier_payment_methods')
                        ->where('supplier_id', $source->id)
                        ->whereIn('type', $existingPaymentTypes)
                        ->delete();
                }
                DB::table('supplier_payment_methods')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 9. Historial de conexiones
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_connection_statuses') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_connection_statuses', 'supplier_id')) {
                DB::table('supplier_connection_statuses')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            }

            // 10. Conexiones FTP/API
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_connections') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_connections', 'supplier_id')) {
                $targetHasConn = DB::table('supplier_connections')->where('supplier_id', $target->id)->exists();
                if (!$targetHasConn) {
                    DB::table('supplier_connections')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
                } else {
                    DB::table('supplier_connections')->where('supplier_id', $source->id)->delete();
                }
            }

            // 11. Eliminar puntuaciones del source
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_scores') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_scores', 'supplier_id')) {
                DB::table('supplier_scores')->where('supplier_id', $source->id)->delete();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_ratings') && \Illuminate\Support\Facades\Schema::hasColumn('supplier_ratings', 'supplier_id')) {
                DB::table('supplier_ratings')->where('supplier_id', $source->id)->delete();
            }

            // 12. Eliminar proveedor de origen de forma definitiva
            $source->forceDelete();

            return $target->fresh();
        });
    }
}
