<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipDetailResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        // $this representa los elementos devueltos por payslipServices->getData
        return [
            'id' => $this['id'] ?? null,
            'name' => $this['name'] ?? '',
            'last_name' => $this['last_name'] ?? '',
            'identification' => $this['identification'] ?? null,
            'base_salary_voucher' => isset($this['base_salary_voucher']) ? (float)$this['base_salary_voucher'] : 0.0,
            'salary_to_pay_voucher' => isset($this['salary_to_pay_voucher']) ? (float)$this['salary_to_pay_voucher'] : 0.0,
            'food_voucher' => isset($this['food_voucher']) ? (float)$this['food_voucher'] : 0.0,
            'health_support_voucher' => isset($this['health_support_voucher']) ? (float)$this['health_support_voucher'] : 0.0,
            'performance_voucher' => isset($this['performance_voucher']) ? (float)$this['performance_voucher'] : 0.0,
            'social_security_voucher' => isset($this['social_security_voucher']) ? (float)$this['social_security_voucher'] : 0.0,
            'employment_voucher' => isset($this['employment_voucher']) ? (float)$this['employment_voucher'] : 0.0,
            'housing_property_benefits_voucher' => isset($this['housing_property_benefits_voucher']) ? (float)$this['housing_property_benefits_voucher'] : 0.0,
            'total_package_usd' => isset($this['total_package_usd']) ? (float)$this['total_package_usd'] : 0.0,
        ];
    }
}
