<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\PaymentRule;
use App\Models\SupplierDiscount;
use App\Models\SupplierLaboratory;
use App\Models\SupplierPaymentMethod;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($validatedData) {
            //$paymentMethodType = $validatedData['supplier_payment_method'] ?? null;
            //$paymentDays = $validatedData['supplier_payment_days'] ?? null;
            //unset($validatedData['supplier_payment_method']);
            //unset($validatedData['supplier_payment_days']);

            $supplier = Supplier::create($validatedData);

            // if ($paymentMethodType) {
            //     SupplierPaymentMethod::create([
            //         'supplier_id' => $supplier->id,
            //         'type' => $paymentMethodType,
            //         'days' => $paymentMethodType === 'credit_days' ? $paymentDays : null,
            //     ]);
            // }

            //$supplier->load('paymentMethods');

            return $supplier;
        });
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
        return DB::transaction(function () use ($supplier, $validatedData) {
            //$paymentMethodType = $validatedData['supplier_payment_method'] ?? null;
            //$paymentDays = $validatedData['supplier_payment_days'] ?? null;
            //unset($validatedData['supplier_payment_method']);
            //unset($validatedData['supplier_payment_days']);

            $supplier->update($validatedData);

            // if ($paymentMethodType) {
            //     $existingPaymentMethod = $supplier->paymentDate;

            //     if ($existingPaymentMethod) {
            //         $existingPaymentMethod->update([
            //             'type' => $paymentMethodType,
            //             'days' => $paymentMethodType === 'credit_days' ? $paymentDays : null,
            //         ]);
            //     } else {
            //         SupplierPaymentMethod::create([
            //             'supplier_id' => $supplier->id,
            //             'type' => $paymentMethodType,
            //             'days' => $paymentMethodType === 'credit_days' ? $paymentDays : null,
            //         ]);
            //     }
            // }

            // $supplier->load('paymentDate');

            return $supplier;
        });
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

        $attributes = [];
        if (isset($data['id']) && $data['id'] > 0) {
            $attributes['id'] = $data['id'];
        }
        $values = [
            'days' => $data['days'],
            'discount_percentage' => $data['discount_percentage'],
            'supplier_id' => $supplier->id,
        ];

        return $supplier->paymentRules()->updateOrCreate($attributes,$values);
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
}
