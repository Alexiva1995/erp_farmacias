<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Http\Resources\DiscountRuleResource;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierLaboratoryQueryService
{
    /**
     * Get the discount rules for a specific supplier laboratory.
     *
     * @param Supplier $lab
     * @return AnonymousResourceCollection
     */
    public function getDiscountRules(Supplier $supplier): AnonymousResourceCollection
    {
        $rules = $supplier->laboratoryLinks()
                        ->with('discountRules.supplierLaboratory.laboratory')
                        ->get()
                        ->pluck('discountRules')
                        ->flatten()
                        ->sortByDesc('created_at')
                        ->values();

        return DiscountRuleResource::collection($rules);
    }
}
