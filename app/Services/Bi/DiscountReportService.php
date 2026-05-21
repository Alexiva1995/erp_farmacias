<?php

namespace App\Services\Bi;

use App\Repositories\DiscountReportRepository;

class DiscountReportService
{
    public function __construct(
        protected DiscountReportRepository $repository
    ) {}

    /**
     * Obtiene todos los datos para el dashboard de descuentos
     */
    public function getDashboardData(array $filters): array
    {
        return [
            'kpis' => $this->repository->getKPIs($filters),
            'distribution' => $this->repository->getDistributionByType($filters),
            'highlights' => $this->repository->getPerformanceHighlights($filters),
            'rankings' => $this->repository->getOfferRankings($filters),
        ];
    }

    /**
     * Obtiene los datos paginados para la auditoría de descuentos
     */
    public function getAuditData(array $filters)
    {
        return $this->repository->getAuditData($filters);
    }
}
