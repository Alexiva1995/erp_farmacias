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
}
