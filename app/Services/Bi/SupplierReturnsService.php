<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\SupplierReturnsRepositoryInterface;
use Carbon\Carbon;

class SupplierReturnsService
{
    public function __construct(
        protected SupplierReturnsRepositoryInterface $repository
    ) {}

    /**
     * Obtiene el reporte completo agrupado por laboratorio.
     * Cada grupo incluye sus lotes, totales y metadatos para la carta de canje.
     */
    public function getReport(array $filters, int $days = 90): array
    {
        $lots = $this->repository->getLotsExpiringSoon($filters, $days);

        if (empty($lots)) {
            return [
                'groups'   => [],
                'summary'  => $this->buildEmptySummary(),
                'metadata' => $this->buildMetadata($days),
            ];
        }

        // Agrupar por laboratorio
        $grouped = [];
        foreach ($lots as $lot) {
            $labId   = $lot['laboratory_id'] ?? 0;
            $labName = $lot['laboratory_name'] ?? 'SIN LABORATORIO';

            if (!isset($grouped[$labId])) {
                $grouped[$labId] = [
                    'laboratory_id'   => $labId,
                    'laboratory_name' => $labName,
                    'lots'            => [],
                    'total_units'     => 0,
                    'total_amount'    => 0.0,
                    'products_count'  => 0,
                ];
            }

            $grouped[$labId]['lots'][]        = $lot;
            $grouped[$labId]['total_units']   += (float) ($lot['quantity'] ?? 0);
            $grouped[$labId]['total_amount']  += (float) ($lot['total_amount'] ?? 0);
        }

        // Calcular productos únicos por laboratorio y ordenar por monto descendente
        foreach ($grouped as &$group) {
            $group['products_count'] = count(
                array_unique(array_column($group['lots'], 'product_id'))
            );
            $group['total_amount']   = round($group['total_amount'], 2);
            $group['total_units']    = round($group['total_units'], 0);
        }
        unset($group);

        // Ordenar grupos por monto en riesgo (mayor primero)
        usort($grouped, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        // Totales globales para KPI cards
        $summary = [
            'total_laboratories' => count($grouped),
            'total_products'     => count(array_unique(array_column($lots, 'product_id'))),
            'total_lots'         => count($lots),
            'total_units'        => round(array_sum(array_column($lots, 'quantity')), 0),
            'total_amount'       => round(array_sum(array_column($lots, 'total_amount')), 2),
        ];

        return [
            'groups'   => array_values($grouped),
            'summary'  => $summary,
            'metadata' => $this->buildMetadata($days),
        ];
    }

    /**
     * Metadatos de contexto para el PDF (fecha de emisión, horizonte, datos farmacia).
     */
    private function buildMetadata(int $days): array
    {
        return [
            'generated_at'  => Carbon::now()->format('d/m/Y'),
            'horizon_days'  => $days,
            'cutoff_date'   => Carbon::now()->addDays($days)->format('d/m/Y'),
            'pharmacy_name' => 'FARMACIA BARRIO SUCRE 2024, C.A.',
            'pharmacy_rif'  => 'J-50540695-7',
            'pharmacy_address' => 'Calle Principal Local 05 (L3) Sector Barrio Sucre, La Fría, Táchira',
            'pharmacy_phone'   => '',
            'buyer_name'       => 'Encargada de Compras',
        ];
    }

    private function buildEmptySummary(): array
    {
        return [
            'total_laboratories' => 0,
            'total_products'     => 0,
            'total_lots'         => 0,
            'total_units'        => 0,
            'total_amount'       => 0.0,
        ];
    }
}
