<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierLaboratory;
use App\Services\Suppliers\SupplierLaboratoryQueryService;
use App\Services\Suppliers\SupplierLaboratoryActionService;
use App\Http\Requests\StoreDiscountRuleRequest;
use App\Models\DiscountRule;
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

    public function storeDiscountRule(StoreDiscountRuleRequest $request)
    {
        $validated = $request->validated();

        $createdRules = [];

        foreach ($validated['rules'] as $rule) {
            $lab = SupplierLaboratory::findOrFail($rule['supplier_laboratory_id']);

            $ruleData = [
                'scale_type' => $validated['scale_type'],
                'min_quantity' => $validated['scale_type'] === 'units' ? $rule['min'] : null,
                'max_quantity' => $validated['scale_type'] === 'units' ? $rule['max'] : null,
                'min_amount' => $validated['scale_type'] === 'amount' ? $rule['min'] : null,
                'max_amount' => $validated['scale_type'] === 'amount' ? $rule['max'] : null,
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
