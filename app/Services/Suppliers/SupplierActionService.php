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
}
