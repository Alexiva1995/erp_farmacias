<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;

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
}
