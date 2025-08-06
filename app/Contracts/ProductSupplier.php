<?php

namespace App\Contracts;

use App\Models\Product;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

interface ProductSupplier
{

    public function consultSupplierByProductWithBetterPrice(Product $product): Collection;

    public function calculatePercentageDifferenceIncrease(float $myPrice, float $supplierPrice): float;

    public function checkIfTheProductHasIncreasedInPrice(float $percentageIncrease, float $maximumPercentageMaximo): Bool;

    public function checkPurchaseOpportunity(float $percentageIncrease, float $maximumPercentageMaximo): Bool;

    public function getSupplierToReplenishTheProducts(Collection $products): array;

    public function supplierProductFormat(Product $product, Supplier $supplier, ModelsProductSupplier $productSupplier): array;

    public function checkTolerance(array $replenishTheProducts): array;
}
