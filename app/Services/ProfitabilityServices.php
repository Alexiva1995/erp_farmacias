<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Profitability;
use App\Models\Product;
use App\Repositories\ProfitabilityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ProfitabilityServices implements Profitability
{

    public function __construct(protected ProfitabilityRepository $profitabilityRepository)
    {
    }

    public function consultOne(): Model|null
    {
        return $this->profitabilityRepository->consultOne();
    }

    public function consultById(string $id): ?Model
    {
        return $this->profitabilityRepository->consultById($id);
    }

    public function store(array $data): Model
    {
        $settings = $this->profitabilityRepository->store($data);
        $percentage = (float) $data['default_profitability_percentage'];

        $this->applyGlobalProfitabilityToAllProductsWithPercentage($percentage);

        return $settings;
    }

    public function storeProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->storeProduct($data);
        $this->updateProductPrice((int) $data['product_id'], (float) $data['profitability_percentage']);
        return $profitability;
    }

    public function editProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->editProduct($data);
        $this->updateProductPrice((int) $data['product_id'], (float) $data['profitability_percentage']);
        return $profitability;
    }

    public function edit(array $data): Model
    {
        return $this->profitabilityRepository->edit($data);
    }

    /**
     * Aplica el porcentaje de rentabilidad global a todos los productos no bloqueados.
     */
    public function applyGlobalProfitabilityToAllProducts(): void
    {
        $settings = $this->consultOne();
        if (!$settings) {
            return;
        }

        $percentage = (float) $settings->default_profitability_percentage;
        $this->applyGlobalProfitabilityToAllProductsWithPercentage($percentage);
    }

    private function applyGlobalProfitabilityToAllProductsWithPercentage(float $percentage): void
    {
        $generalSettings = \Illuminate\Support\Facades\DB::table('general_settings')->first();
        $useCompound = $generalSettings && isset($generalSettings->profitability_calculation_type) && $generalSettings->profitability_calculation_type === 'compound';
        $roundUsdUp = $generalSettings && !empty($generalSettings->round_usd_up);
        $precision = $this->getPricePrecision($generalSettings);

        $query = Product::query()
            ->whereDoesntHave('profitability')
            ->orWhereHas('profitability', fn ($q) => $q->where('is_locked', '!=', 1));

        if (!$useCompound) {
            $multiplier = 1 + ($percentage / 100);
            if ($roundUsdUp) {
                $query->update([
                    'sale_price' => \Illuminate\Support\Facades\DB::raw("CEIL(ROUND(unit_cost * {$multiplier}, 4))")
                ]);
            } else {
                $query->update([
                    'sale_price' => \Illuminate\Support\Facades\DB::raw("ROUND(unit_cost * {$multiplier}, {$precision})")
                ]);
            }
            return;
        }

        $settings = $this->consultOne();

        $query->with('profitability')->chunkById(500, function ($products) use ($settings, $generalSettings) {
            foreach ($products as $product) {
                $productProfitability = $product->profitability;
                $shippingCost = $productProfitability && $productProfitability->shipping_cost !== null ? (float)$productProfitability->shipping_cost : ($settings ? (float)$settings->shipping_cost : 0.0);
                $packagingCost = $productProfitability && $productProfitability->packaging_cost !== null ? (float)$productProfitability->packaging_cost : ($settings ? (float)$settings->packaging_cost : 0.0);
                $expenseMargin = $productProfitability && $productProfitability->expense_margin !== null ? (float)$productProfitability->expense_margin : ($settings ? (float)$settings->expense_margin : 0.0);
                $profitMargin = $productProfitability && $productProfitability->profit_margin !== null ? (float)$productProfitability->profit_margin : ($settings ? (float)$settings->profit_margin : 0.0);
                $taxUsa = $productProfitability && $productProfitability->tax_usa !== null ? (float)$productProfitability->tax_usa : ($settings ? (float)$settings->tax_usa : 0.0);

                $costWithTax = $product->unit_cost * (1.0 + ($taxUsa / 100.0));
                $fixedExpenseAmount = $costWithTax * ($expenseMargin / 100.0);
                $profitDenominator = 1.0 - ($profitMargin / 100.0);
                if ($profitDenominator <= 0.0) {
                    $profitDenominator = 0.01;
                }

                $newPrice = ($costWithTax + $shippingCost + $packagingCost + $fixedExpenseAmount) / $profitDenominator;

                $product->sale_price = $this->roundSalePrice($newPrice, $generalSettings);
                $product->save();
            }
        });
    }

    private function updateProductPrice(int $productId, float $percentage): void
    {
        $product = Product::with('profitability')->find($productId);
        if (!$product) {
            return;
        }

        $generalSettings = \Illuminate\Support\Facades\DB::table('general_settings')->first();
        $useCompound = $generalSettings && isset($generalSettings->profitability_calculation_type) && $generalSettings->profitability_calculation_type === 'compound';

        if ($useCompound) {
            $settings = $this->consultOne();
            $productProfitability = $product->profitability;

            $shippingCost = $productProfitability && $productProfitability->shipping_cost !== null ? (float)$productProfitability->shipping_cost : ($settings ? (float)$settings->shipping_cost : 0.0);
            $packagingCost = $productProfitability && $productProfitability->packaging_cost !== null ? (float)$productProfitability->packaging_cost : ($settings ? (float)$settings->packaging_cost : 0.0);
            $expenseMargin = $productProfitability && $productProfitability->expense_margin !== null ? (float)$productProfitability->expense_margin : ($settings ? (float)$settings->expense_margin : 0.0);
            $profitMargin = $productProfitability && $productProfitability->profit_margin !== null ? (float)$productProfitability->profit_margin : ($settings ? (float)$settings->profit_margin : 0.0);
            $taxUsa = $productProfitability && $productProfitability->tax_usa !== null ? (float)$productProfitability->tax_usa : ($settings ? (float)$settings->tax_usa : 0.0);

            $costWithTax = $product->unit_cost * (1.0 + ($taxUsa / 100.0));
            $fixedExpenseAmount = $costWithTax * ($expenseMargin / 100.0);
            $profitDenominator = 1.0 - ($profitMargin / 100.0);
            if ($profitDenominator <= 0.0) {
                $profitDenominator = 0.01;
            }

            $newPrice = ($costWithTax + $shippingCost + $packagingCost + $fixedExpenseAmount) / $profitDenominator;
        } else {
            $newPrice = $product->unit_cost * (1 + ($percentage / 100));
        }

        $product->sale_price = $this->roundSalePrice($newPrice, $generalSettings);
        $product->save();
    }

    /**
     * Determina la cantidad de decimales para precios USD según el tipo de negocio.
     */
    private function getPricePrecision(?object $generalSettings): int
    {
        $isRestaurant = $generalSettings && in_array($generalSettings->business_type ?? null, ['restaurant', 'fast_food'], true);

        return $isRestaurant ? 4 : 2;
    }

    /**
     * Redondea el precio de venta según configuración global y precisión del negocio.
     */
    private function roundSalePrice(float $newPrice, ?object $generalSettings): float
    {
        $roundUsdUp = $generalSettings && !empty($generalSettings->round_usd_up);
        if ($roundUsdUp) {
            return ceil(round($newPrice, 4));
        }

        return round($newPrice, $this->getPricePrecision($generalSettings));
    }
}
