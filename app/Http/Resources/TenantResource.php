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
                $this->run(function () use (&$vertical) {
                    $setting = GeneralSetting::select('business_type')->first();
                    if ($setting?->business_type) {
                        $vertical = $setting->business_type;
                    }
                });
            } catch (\Throwable $e) {
                // Silencioso ante excepciones de conexión
            }
        }

        return [
            'id' => $this->id,
            'company_name' => $this->company_name ?? $this->id,
            'vertical' => $vertical ?: 'pharmacy',
            'plan_id' => $this->plan_id,
            'domains' => $this->whenLoaded('domains', function () {
                return $this->domains->map(fn($d) => [
                    'id' => $d->id,
                    'domain' => $d->domain,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
