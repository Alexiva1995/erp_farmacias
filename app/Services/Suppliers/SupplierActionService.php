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
        if (config('catalog.role') === 'slave') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterSupplier = $masterClient->registerSupplierInMaster($validatedData);
                if (!empty($masterSupplier['id'])) {
                    $validatedData['id'] = (int) $masterSupplier['id'];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo sincronizar proveedor con Master Catalog: ' . $e->getMessage());
            }
        }

        return $this->supplierRepository->create($validatedData);
    }

    /**
     * Actualiza un proveedor existente.
     *
     * @param Supplier $supplier
     * @param array $validatedData
     * @return Supplier
     */
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
        return ProductSupplier::whereDate('updated_at', '<', $date)
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
            DB::table('invoices')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('retentions')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 2. Órdenes automáticas y reposición
            DB::table('auto_orders')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('auto_replenishment_configs')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 3. Movimientos de inventario, lotes, vencimientos y psicotrópicos
            DB::table('inventory_movements')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('product_lots')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('expirations')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('psychotropic_controls')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 4. Productos asociados
            DB::table('products')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 5. Ofertas de proveedor (product_suppliers)
            // Evitar duplicados si existieran registros idénticos
            $targetProductIds = DB::table('product_suppliers')
                ->where('supplier_id', $target->id)
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->toArray();

            if (!empty($targetProductIds)) {
                // Eliminar del source aquellos que ya existan en target para no duplicar
                DB::table('product_suppliers')
                    ->where('supplier_id', $source->id)
                    ->whereIn('product_id', $targetProductIds)
                    ->delete();
            }
            DB::table('product_suppliers')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 6. Laboratorios vinculados (evitar duplicados con unique ['supplier_id', 'laboratory_id'])
            $existingLabIds = DB::table('supplier_laboratories')
                ->where('supplier_id', $target->id)
                ->pluck('laboratory_id')
                ->toArray();

            if (!empty($existingLabIds)) {
                DB::table('supplier_laboratories')
                    ->where('supplier_id', $source->id)
                    ->whereIn('laboratory_id', $existingLabIds)
                    ->delete();
            }
            DB::table('supplier_laboratories')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 7. Reglas de pago y descuentos
            DB::table('payment_rules')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('supplier_discounts')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            DB::table('suppliers_config_products')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 8. Métodos de pago
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

            // 9. Historial de conexiones
            DB::table('supplier_connection_statuses')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);

            // 10. Conexiones FTP/API: si el target no tiene conexiones, mover las del source
            $targetHasConn = DB::table('supplier_connections')->where('supplier_id', $target->id)->exists();
            if (!$targetHasConn) {
                DB::table('supplier_connections')->where('supplier_id', $source->id)->update(['supplier_id' => $target->id]);
            } else {
                DB::table('supplier_connections')->where('supplier_id', $source->id)->delete();
            }

            // 11. Eliminar puntuaciones del source
            DB::table('supplier_scores')->where('supplier_id', $source->id)->delete();
            DB::table('supplier_ratings')->where('supplier_id', $source->id)->delete();

            // 12. Eliminar proveedor de origen de forma definitiva
            $source->forceDelete();

            return $target->fresh();
        });
    }
}
