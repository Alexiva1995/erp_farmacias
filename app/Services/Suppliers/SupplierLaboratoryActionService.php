<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\DiscountRule;
use App\Models\SupplierLaboratory;
use Illuminate\Validation\ValidationException;

class SupplierLaboratoryActionService
{
    public function createDiscountRule(SupplierLaboratory $lab, array $data): DiscountRule
    {
        return $lab->discountRules()->create($data);
    }
}
