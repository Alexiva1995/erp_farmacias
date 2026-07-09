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
            // Formula: Price = Cost * (1 + (Percentage / 100))
            $newPrice = $product->unit_cost * (1 + ($percentage / 100));
            $product->sale_price = $newPrice;
            $product->save();
        }
    }
}
