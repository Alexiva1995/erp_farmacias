<?php

namespace App\Contracts;

use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Collection;

interface ProductSupplier
{

    public function consultSupplierByProductWithBetterPrice(object|array $product, string $conDescuento): Collection;

    public function calculatePercentageDifferenceIncrease(float $myPrice, float $supplierPrice): float;

    public function checkIfTheProductHasIncreasedInPrice(float $percentageIncrease, float $maximumPercentageMaximo): bool|null;

    public function checkPurchaseOpportunity(float $percentageIncrease, float $maximumPercentageMaximo): bool;

    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento, bool $skipAiMatch = false): array;

    public function getSupplierToReplenishTheProductsWithoutValidateSolicitar(Collection $products, string $conDescuento): array;

    public function supplierProductFormat(object|array $product, Supplier $supplier, ModelsProductSupplier $productSupplier, int $repuesto): array;

    public function checkTolerance(array $replenishTheProducts, string $conDescuento): array;

    public function obtainProductsWithUniqueMarketOpportunities(array $productos): array;

    public function getTheLowestLotCost(Collection $productos): array;
}
