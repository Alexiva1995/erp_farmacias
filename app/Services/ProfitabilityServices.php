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

        $productIds = Product::query()
            ->whereDoesntHave('profitability')
            ->orWhereHas('profitability', fn ($q) => $q->where('is_locked', '!=', 1))
            ->pluck('id');

        foreach ($productIds as $productId) {
            $this->updateProductPrice((int) $productId, $percentage);
        }

        return $settings;
    }

    public function storeProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->storeProduct($data);
        $this->updateProductPrice($data['product_id'], $data['profitability_percentage']);
        return $profitability;
    }

    public function editProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->editProduct($data);
        $this->updateProductPrice($data['product_id'], $data['profitability_percentage']);
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

        // Obtener IDs de productos que no tienen rentabilidad o que no están bloqueados
        $productIds = Product::query()
            ->whereDoesntHave('profitability')
            ->orWhereHas('profitability', fn ($q) => $q->where('is_locked', '!=', 1))
            ->pluck('id');

        foreach ($productIds as $productId) {
            $this->updateProductPrice((int) $productId, $percentage);
        }
    }

    private function updateProductPrice(int $productId, float $percentage): void
    {
        $product = \App\Models\Product::find($productId);
        if ($product) {
            $generalSettings = \Illuminate\Support\Facades\DB::table('general_settings')->first();
            $isMinimarket = $generalSettings && $generalSettings->business_type === 'minimarket';

            if ($isMinimarket) {
                $settings = $this->consultOne();
                $productProfitability = \App\Models\ProductProfitability::where('product_id', $productId)->first();

                $shippingCost = $productProfitability && $productProfitability->shipping_cost !== null ? (float)$productProfitability->shipping_cost : ($settings ? (float)$settings->shipping_cost : 0.0);
                $packagingCost = $productProfitability && $productProfitability->packaging_cost !== null ? (float)$productProfitability->packaging_cost : ($settings ? (float)$settings->packaging_cost : 0.0);
                $expenseMargin = $productProfitability && $productProfitability->expense_margin !== null ? (float)$productProfitability->expense_margin : ($settings ? (float)$settings->expense_margin : 0.0);
                $profitMargin = $productProfitability && $productProfitability->profit_margin !== null ? (float)$productProfitability->profit_margin : ($settings ? (float)$settings->profit_margin : 0.0);

                $totalMargin = ($expenseMargin + $profitMargin) / 100.0;
                if ($totalMargin >= 1.0) {
                    $totalMargin = 0.99; // Evitar división por cero
                }

                $newPrice = ($product->unit_cost + $shippingCost + $packagingCost) / (1.0 - $totalMargin);
            } else {
                $newPrice = $product->unit_cost * (1 + ($percentage / 100));
            }

            $product->sale_price = round($newPrice, 2);
            $product->save();
        }
    }
}
