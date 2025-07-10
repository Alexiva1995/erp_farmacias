<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\PaymentRule;

class SupplierActionService
{
    /**
     *
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
     * 
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

    public function updatePaymentRule(Supplier $supplier, array $validatedData): PaymentRule
    {
        return $supplier->paymentRule()->updateOrCreate([], $validatedData);
    }
}
