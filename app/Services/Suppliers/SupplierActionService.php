<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\PaymentRule;
use App\Models\SupplierDiscount;
use App\Models\SupplierLaboratory;

class SupplierActionService
{
    /**
     * Crea un nuevo proveedor.
     *
     * @param array $validatedData 
     * @return Supplier 
     */
    public function createSupplier(array $validatedData): Supplier
    {
        $supplier = Supplier::create($validatedData);

        return $supplier;
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
        $supplier->update($validatedData);

        return $supplier;
    }

    /**
     * Elimina un proveedor.
     *
     * @param Supplier $supplier
     */
    public function deleteSupplier(Supplier $supplier): void
    {
        $supplier->delete();
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
        return $supplier->paymentRules()->create( $data);
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
        return $supplier->laboratoryLinks()->create($validatedData);
    }

    public function createDiscount(Supplier $supplier, array $data): SupplierDiscount
    {
        return $supplier->discounts()->create( $data);
    }
}
