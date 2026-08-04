<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResignationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee_name ?? ($this->employee ? $this->employee->first_name . ' ' . $this->employee->last_name : 'N/A'),
            'employee_identification' => $this->employee_identification ?? ($this->employee->id_number ?? 'N/A'),
            'employee_email' => $this->employee_email ?? ($this->employee->email ?? ''),
            'employee_position' => $this->employee_position ?? ($this->employee->position ?? 'Cargo no especificado'),
            'resignation_type' => $this->resignation_type,
            'effective_date' => $this->effective_date ? $this->effective_date->format('Y-m-d') : null,
            'request_date' => $this->request_date ? $this->request_date->format('Y-m-d') : null,
            'employee_status' => $this->employee_status ?? ($this->employee && $this->employee->is_active ? 'Activo' : 'Inactivo'),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }
}
