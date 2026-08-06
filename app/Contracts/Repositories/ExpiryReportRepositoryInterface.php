<?php

namespace App\Contracts\Repositories;

interface ExpiryReportRepositoryInterface
{
    public function getExpiryHorizon(array $filters): array;
    public function getRealLossAnalysis(array $filters): array;
    public function getOverstockWarning(array $filters): array;
    public function getCurrentExpiredStock(array $filters): array;
}
