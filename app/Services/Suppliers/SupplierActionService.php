<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\PaymentRule;
use App\Models\SupplierDiscount;
use App\Models\SupplierLaboratory;
use App\Models\SupplierPaymentMethod;
use App\Contracts\Repositories\SupplierRepositoryInterface;

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
     * @param array $validatedData
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
        //return $supplier->laboratoryLinks()->create($validatedData);
    }

    public function createDiscount(Supplier $supplier, array $data): SupplierDiscount
    {
        return $supplier->discounts()->create($data);
    }
    public function applyGlobalDiscount(Supplier $supplier, float $percentage)
    {
        $factor = 1 - ($percentage / 100);
        return \Illuminate\Support\Facades\DB::table('product_suppliers')
            ->where('supplier_id', $supplier->id)
            ->update([
                'unit_cost_with_discount' => \Illuminate\Support\Facades\DB::raw("
                    ROUND(
                        COALESCE(NULLIF(unit_cost_with_discount, 0), unit_cost) * {$factor}, 
                        2
                    )
                "),
                'unit_cost_usd_with_discount' => \Illuminate\Support\Facades\DB::raw("
                    ROUND(
                        COALESCE(NULLIF(unit_cost_usd_with_discount, 0), unit_cost_usd) * {$factor}, 
                        2
                    )
                "),
                'updated_at' => now(),
            ]);
    }
    public function deleteProductsOlderThan(string $date)
    {
        return ProductSupplier::whereDate('updated_at', '<', $date)
            ->whereDoesntHave('autoOrderDetails')
            ->delete();
    }
}
