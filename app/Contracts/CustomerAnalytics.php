<?php

namespace App\Contracts;

interface CustomerAnalytics
{
    public function getKpis(array $filters): array;
    public function getGrowthData(array $filters): array;
    public function getFrequencyData(array $filters): array;
    public function getValueSegmentation(array $filters): array;
    public function getCohortData(array $filters): array;
    public function getRfmData(array $filters): array;
}
