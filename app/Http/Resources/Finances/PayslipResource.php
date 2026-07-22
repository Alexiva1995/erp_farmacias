<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'payslip_date' => $this->payslip_date instanceof \Carbon\Carbon 
                ? $this->payslip_date->toDateString() 
                : $this->payslip_date,
            'exchange_rate' => $this->exchange_rate !== null ? (float)$this->exchange_rate : null,
            'total' => (float)$this->total,
            'status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
