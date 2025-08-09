<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SupplierHealthService
{
    public function check(): array
    {
        $results = [];

        try {
            // GET
            Supplier::limit(1)->get();
            $results['GET'] = 'OK';

            // POST
            $sampleCreate = [
                'name' => 'TEST API',
                'sales_phone' => '123456789',
                'collections_phone' => '987654321',
                'dispatch_days' => ['monday'],
                'order_days' => ['tuesday'],
                'payment_method' => 'Bs',
            ];

            DB::beginTransaction();
            Supplier::create($sampleCreate);
            DB::rollBack();
            $results['POST'] = 'OK';

            // PUT
            $supplier = Supplier::first();
            if ($supplier) {
                DB::beginTransaction();
                $supplier->update(['name' => 'MODIFICADO API']);
                DB::rollBack();
                $results['PUT'] = 'OK';
            } else {
                $results['PUT'] = 'SKIPPED (No hay proveedores)';
            }

            // DELETE
            if ($supplier) {
                DB::beginTransaction();
                $supplier->delete();
                DB::rollBack();
                $results['DELETE'] = 'OK';
            } else {
                $results['DELETE'] = 'SKIPPED (No hay proveedores)';
            }

        } catch (\Throwable $e) {
            Log::error('[API Check Failed] ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}
