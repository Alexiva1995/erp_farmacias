<?php

namespace App\Contracts;

use App\Models\Product;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProductSupplier
{

    public function consultSupplierByProductWithBetterPrice(Product $product): Collection;

    public function calculatePercentageDifferenceIncrease(float $myPrice, float $supplierPrice): float;

    public function checkIfTheProductHasIncreasedInPrice(float $percentageIncrease, float $maximumPercentageMaximo): Bool;

    public function checkPurchaseOpportunity(float $percentageIncrease, float $maximumPercentageMaximo): Bool;

    public function getSupplierToReplenishTheProducts(Collection $products): array;

    public function supplierProductFormat(Product $product, Supplier $supplier, ModelsProductSupplier $productSupplier, int $repuesto): array;

    public function checkTolerance(array $replenishTheProducts): array;

    public function obtainProductsWithUniqueMarketOpportunities(array $productos): array;
}
