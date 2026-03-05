<?php

namespace App\Services\Accounting;

use App\Contracts\Accounting\BalanceRepositoryInterface;

class BalanceService
{
    public function __construct(
        private BalanceRepositoryInterface $balanceRepository
    ) {}

    public function getFullBalance(): array
    {
        $assetsData = $this->balanceRepository->getAssets();
        $liabilitiesData = $this->balanceRepository->getLiabilities();
        $depreciation = $this->balanceRepository->getDepreciation();

        $totalAssetsBruto = array_sum($assetsData);
        $totalAssetsNeto = $totalAssetsBruto - $depreciation;

        $totalLiabilities = array_sum($liabilitiesData);
        $equity = $totalAssetsNeto - $totalLiabilities;

        return [
            'assets' => [
                'details' => $assetsData,
                'total_bruto' => $totalAssetsBruto,
                'total_neto' => $totalAssetsNeto,
                'depreciation' => $depreciation
            ],
            'liabilities' => [
                'details' => $liabilitiesData,
                'total' => $totalLiabilities
            ],
            'equity' => $equity,
            'ratios' => $this->calculateRatios($totalAssetsNeto, $totalLiabilities),
            'calculated_at' => now()->toIso8601String()
        ];
    }

    private function calculateRatios(float $assets, float $liabilities): array
    {
        return [
            'liquidity' => $liabilities > 0 ? round($assets / $liabilities, 2) : $assets,
            'solvency' => $liabilities > 0 ? round(($assets - $liabilities) / $liabilities, 2) : 100,
        ];
    }
}
