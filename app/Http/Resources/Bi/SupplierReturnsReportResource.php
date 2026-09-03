<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea la respuesta completa del reporte de devoluciones a proveedores.
 * Aplica tipos estrictos, encapsula grupos de laboratorio y expone solo
 * los campos necesarios para la UI y el generador de PDF.
 */
class SupplierReturnsReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $groups = collect($this['groups'] ?? [])->map(fn($group) => [
            'laboratory_id'   => (int) ($group['laboratory_id'] ?? 0),
            'laboratory_name' => (string) ($group['laboratory_name'] ?? 'SIN LABORATORIO'),
            'products_count'  => (int) ($group['products_count'] ?? 0),
            'total_units'     => (float) ($group['total_units'] ?? 0),
            'total_amount'    => (float) ($group['total_amount'] ?? 0),
            // Aplica el resource de lote a cada ítem del grupo
            'lots' => SupplierReturnsLotResource::collection(
                collect($group['lots'] ?? [])
            )->resolve($request),
        ])->values()->all();

        $summary  = $this['summary'] ?? [];
        $metadata = $this['metadata'] ?? [];

        return [
            'groups' => $groups,
            'summary' => [
                'total_laboratories' => (int) ($summary['total_laboratories'] ?? 0),
                'total_products'     => (int) ($summary['total_products'] ?? 0),
                'total_lots'         => (int) ($summary['total_lots'] ?? 0),
                'total_units'        => (float) ($summary['total_units'] ?? 0),
                'total_amount'       => (float) ($summary['total_amount'] ?? 0),
            ],
            'metadata' => [
                'generated_at'     => $metadata['generated_at'] ?? '',
                'horizon_days'     => (int) ($metadata['horizon_days'] ?? 90),
                'cutoff_date'      => $metadata['cutoff_date'] ?? '',
                'pharmacy_name'    => $metadata['pharmacy_name'] ?? '',
                'pharmacy_rif'     => $metadata['pharmacy_rif'] ?? '',
                'pharmacy_address' => $metadata['pharmacy_address'] ?? '',
                'pharmacy_phone'   => $metadata['pharmacy_phone'] ?? '',
                'buyer_name'       => $metadata['buyer_name'] ?? '',
            ],
        ];
    }
}
