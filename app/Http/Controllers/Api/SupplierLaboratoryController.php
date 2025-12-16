<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierLaboratory;
use App\Services\Suppliers\SupplierLaboratoryQueryService;
use App\Services\Suppliers\SupplierLaboratoryActionService;
use App\Http\Requests\StoreDiscountRuleRequest;
use App\Models\DiscountRule;
use App\Models\Laboratory;
use App\Models\Supplier;

class SupplierLaboratoryController extends Controller
{
    public function __construct(
        private SupplierLaboratoryQueryService $supplierLaboratoryQueryService,
        private SupplierLaboratoryActionService $supplierLaboratoryActionService
    ) {
    }

    public function getDiscountRules(Supplier $supplier)
    {
        $rules = $this->supplierLaboratoryQueryService->getDiscountRules($supplier);

        return response()->json(['discount_rules' => $rules]);
    }

        public function storeDiscountRule(StoreDiscountRuleRequest $request, $supplierLaboratoryId)
    {
        $validated = $request->validated();

        $createdRules = [];

        foreach ($validated['rules'] as $rule) {
            $laboratoryId = $rule['laboratory']['id'];

            $lab = SupplierLaboratory::firstOrCreate(
                [
                'supplier_id' => $supplierLaboratoryId,
                'laboratory_id' => $laboratoryId,
                ],
            );

            $ruleData = [
                'scale_type' => $rule['scale_type']['id'],
                'min_quantity' => $rule['scale_type']['id'] === 'units' ? $rule['min'] : null,
                'max_quantity' => $rule['scale_type']['id'] === 'units' ? $rule['max'] : null,
                'min_amount' => $rule['scale_type']['id'] === 'amount' ? $rule['min'] : null,
                'max_amount' => $rule['scale_type']['id'] === 'amount' ? $rule['max'] : null,
                'discount_percentage' => $rule['discount_percentage'],
            ];

            $createdRules[] = $this->supplierLaboratoryActionService->createDiscountRule($lab, $ruleData);
        }

        return response()->json([
            'message' => 'Reglas registradas correctamente',
            'rules' => $createdRules,
        ]);
    }
}
