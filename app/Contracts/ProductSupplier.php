<?php

namespace App\Contracts;

use App\Models\Product;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProductSupplier
{

    public function consultSupplierByProductWithBetterPrice(Product $product, string $conDescuento): Collection;

    public function calculatePercentageDifferenceIncrease(float $myPrice, float $supplierPrice): float;

    public function checkIfTheProductHasIncreasedInPrice(float $percentageIncrease, float $maximumPercentageMaximo): Bool | null;

    public function checkPurchaseOpportunity(float $percentageIncrease, float $maximumPercentageMaximo): Bool;

    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento): array;

    public function getSupplierToReplenishTheProductsWithoutValidateSolicitar(Collection $products, string $conDescuento): array;

    public function supplierProductFormat(Product $product, Supplier $supplier, ModelsProductSupplier $productSupplier, int $repuesto): array;

    public function checkTolerance(array $replenishTheProducts, string $conDescuento): array;

    public function obtainProductsWithUniqueMarketOpportunities(array $productos): array;

    public function getTheLowestLotCost(Collection $productos): array;
}
