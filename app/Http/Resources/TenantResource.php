<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vertical = $this->vertical;

        // Si la vertical no está guardada en la tabla central de tenants, resolverla desde la BD del tenant
        if (empty($vertical)) {
            try {
                $dbName = $this->database()->getName();
                $dbExists = false;
                try {
                    $dbExists = $this->database()->manager()->databaseExists($dbName);
                } catch (\Throwable) {
                    $dbExists = false;
                }

                if ($dbExists) {
                    $this->run(function () use (&$vertical) {
                        $setting = GeneralSetting::select('business_type')->first();
                        if ($setting?->business_type) {
                            $vertical = $setting->business_type;
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Silencioso ante excepciones de conexión
            }
        }

        $domains = $this->whenLoaded('domains', function () {
            if ($this->domains->isEmpty() && !empty($this->id)) {
                return [
                    [
                        'id' => 0,
                        'domain' => "{$this->id}.tovaerp.com",
                    ],
                ];
            }

            return $this->domains->map(fn($d) => [
                'id' => $d->id,
                'domain' => $d->domain,
            ])->values()->all();
        });

        return [
            'id' => $this->id,
            'company_name' => $this->company_name ?? $this->id,
            'database' => $this->database()->getName(),
            'vertical' => $vertical ?: 'pharmacy',
            'plan_id' => $this->plan_id,
            'parent_tenant_id' => $this->parent_tenant_id,
            'branch_code' => $this->branch_code,
            'can_create_branches' => (bool) ($this->can_create_branches ?? false),
            'max_branches' => (int) ($this->max_branches ?? 5),
            'is_branch_active' => (bool) ($this->is_branch_active ?? true),
            'domains' => $domains,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
