<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Profitability;
use App\Models\CategoryProfitability;
use App\Models\Product;
use App\Models\ProfitabilitySettings;
use App\Repositories\ProfitabilityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfitabilityServices implements Profitability
{
    public function __construct(protected ProfitabilityRepository $profitabilityRepository)
    {
    }

    public function consultOne(): ?Model
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

        $this->applyGlobalProfitabilityToAllProductsWithPercentage($percentage, $settings);

        return $settings;
    }

    public function storeProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->storeProduct($data);
        $this->updateProductPrice((int) $data['product_id']);

        return $profitability;
    }

    public function editProduct(array $data): Model
    {
        $profitability = $this->profitabilityRepository->editProduct($data);
        $this->updateProductPrice((int) $data['product_id']);

        return $profitability;
    }

    public function edit(array $data): Model
    {
        return $this->profitabilityRepository->edit($data);
    }

    public function getCategoriesWithProfitability(): Collection
    {
        return $this->profitabilityRepository->getCategoriesWithProfitability();
    }

    public function upsertCategoryProfitability(array $data): Model
    {
        $categoryProfitability = $this->profitabilityRepository->upsertCategoryProfitability($data);
        $this->applyCategoryProfitability((int) $data['category_id']);

        return $categoryProfitability->load('category:id,name');
    }

    public function deleteCategoryProfitability(int $categoryId): void
    {
        $this->profitabilityRepository->deleteCategoryProfitability($categoryId);
        $this->applyCategoryProfitability($categoryId);
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
        $this->applyGlobalProfitabilityToAllProductsWithPercentage($percentage, $settings);
    }

    /**
     * Recalcula el precio de venta de todos los productos no bloqueados de una categoría.
     */
    public function applyCategoryProfitability(int $categoryId): int
    {
        $generalSettings = DB::table('general_settings')->first();
        $settings = $this->consultOne();
        $categoryProfitability = Schema::hasTable('category_profitability')
            ? CategoryProfitability::where('category_id', $categoryId)->first()
            : null;

        $updated = 0;

        Product::query()
            ->where('category_id', $categoryId)
            ->where(function ($q) {
                $q->whereDoesntHave('profitability')
                    ->orWhereHas('profitability', fn ($sub) => $sub->where('is_locked', '!=', 1));
            })
            ->with('profitability')
            ->chunkById(500, function ($products) use ($settings, $categoryProfitability, $generalSettings, &$updated) {
                foreach ($products as $product) {
                    $newPrice = $this->calculateSalePriceForProduct(
                        $product,
                        null,
                        $settings,
                        $categoryProfitability,
                        $generalSettings
                    );

                    if ((float) $product->sale_price !== $newPrice) {
                        $product->sale_price = $newPrice;
                        $product->save();
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /**
     * Resuelve los parámetros efectivos de rentabilidad de un producto con herencia en cascada:
     * Producto bloqueado -> Categoría activa -> Configuración Global.
     */
    public function resolveEffectiveParameters(
        Product $product,
        ?ProfitabilitySettings $globalSettings = null,
        ?CategoryProfitability $categoryProfitability = null
    ): array {
        if ($globalSettings === null && Schema::hasTable('profitability_settings')) {
            $globalSettings = $this->consultOne();
        }

        $productProf = $product->relationLoaded('profitability')
            ? $product->profitability
            : $product->profitability()->first();

        $isLocked = $productProf && (int) $productProf->is_locked === 1;

        if ($categoryProfitability === null && $product->category_id && Schema::hasTable('category_profitability')) {
            if ($product->relationLoaded('category') && $product->category && $product->category->relationLoaded('profitability')) {
                $categoryProfitability = $product->category->profitability;
            } else {
                $categoryProfitability = CategoryProfitability::where('category_id', $product->category_id)->first();
            }
        }

        $activeCategory = ($categoryProfitability && $categoryProfitability->status === 'open')
            ? $categoryProfitability
            : null;

        $fields = [
            'profitability_percentage' => 'default_profitability_percentage',
            'shipping_cost' => 'shipping_cost',
            'packaging_cost' => 'packaging_cost',
            'expense_margin' => 'expense_margin',
            'profit_margin' => 'profit_margin',
            'tax_usa' => 'tax_usa',
        ];

        $resolved = [];
        $usedCategory = false;

        foreach ($fields as $field => $globalField) {
            if ($isLocked && $productProf->{$field} !== null) {
                $resolved[$field] = (float) $productProf->{$field};
            } elseif ($activeCategory && $activeCategory->{$field} !== null) {
                $resolved[$field] = (float) $activeCategory->{$field};
                $usedCategory = true;
            } elseif ($globalSettings && $globalSettings->{$globalField} !== null) {
                $resolved[$field] = (float) $globalSettings->{$globalField};
            } else {
                $resolved[$field] = 0.0;
            }
        }

        $resolved['source'] = $isLocked
            ? 'locked'
            : ($usedCategory ? 'category' : 'global');

        return $resolved;
    }

    /**
     * Calcula el precio de venta efectivo de un producto respetando la fórmula global (simple o compuesta)
     * y la cascada de parámetros (Producto bloqueado -> Categoría -> Global).
     */
    public function calculateSalePriceForProduct(
        Product $product,
        ?float $unitCostOverride = null,
        ?ProfitabilitySettings $globalSettings = null,
        ?CategoryProfitability $categoryProfitability = null,
        ?object $generalSettings = null
    ): float {
        $unitCost = $unitCostOverride ?? (float) ($product->unit_cost ?? 0);
        if ($unitCost <= 0) {
            return 0.0;
        }

        if ($generalSettings === null && Schema::hasTable('general_settings')) {
            $generalSettings = DB::table('general_settings')->first();
        }

        $useCompound = $generalSettings
            && isset($generalSettings->profitability_calculation_type)
            && $generalSettings->profitability_calculation_type === 'compound';

        $params = $this->resolveEffectiveParameters($product, $globalSettings, $categoryProfitability);

        if ($useCompound) {
            $costWithTax = $unitCost * (1.0 + ($params['tax_usa'] / 100.0));
            $fixedExpenseAmount = $costWithTax * ($params['expense_margin'] / 100.0);
            $profitDenominator = 1.0 - ($params['profit_margin'] / 100.0);
            if ($profitDenominator <= 0.0) {
                $profitDenominator = 0.01;
            }

            $newPrice = ($costWithTax + $params['shipping_cost'] + $params['packaging_cost'] + $fixedExpenseAmount) / $profitDenominator;
        } else {
            $newPrice = $unitCost * (1 + ($params['profitability_percentage'] / 100));
        }

        return $this->roundSalePrice($newPrice, $generalSettings);
    }

    private function applyGlobalProfitabilityToAllProductsWithPercentage(
        float $percentage,
        ?ProfitabilitySettings $settings = null
    ): void {
        $generalSettings = DB::table('general_settings')->first();
        $useCompound = $generalSettings
            && isset($generalSettings->profitability_calculation_type)
            && $generalSettings->profitability_calculation_type === 'compound';
        $roundUsdUp = $generalSettings && !empty($generalSettings->round_usd_up);
        $precision = $this->getPricePrecision($generalSettings);

        $categoryMap = Schema::hasTable('category_profitability')
            ? CategoryProfitability::where('status', 'open')->get()->keyBy('category_id')
            : collect();

        $query = Product::query()->where(function ($q) {
            $q->whereDoesntHave('profitability')
                ->orWhereHas('profitability', fn ($sub) => $sub->where('is_locked', '!=', 1));
        });

        // Vía rápida SQL solo cuando el cálculo es simple y no existen reglas activas por categoría
        if (!$useCompound && $categoryMap->isEmpty()) {
            $multiplier = 1 + ($percentage / 100);
            if ($roundUsdUp) {
                $query->update([
                    'sale_price' => DB::raw("CEIL(ROUND(unit_cost * {$multiplier}, 4))"),
                ]);
            } else {
                $query->update([
                    'sale_price' => DB::raw("ROUND(unit_cost * {$multiplier}, {$precision})"),
                ]);
            }

            return;
        }

        $settings = $settings ?? $this->consultOne();

        $query->with('profitability')->chunkById(500, function ($products) use ($settings, $categoryMap, $generalSettings) {
            foreach ($products as $product) {
                $categoryProfitability = $product->category_id ? $categoryMap->get($product->category_id) : null;
                $newPrice = $this->calculateSalePriceForProduct(
                    $product,
                    null,
                    $settings,
                    $categoryProfitability,
                    $generalSettings
                );

                if ((float) $product->sale_price !== $newPrice) {
                    $product->sale_price = $newPrice;
                    $product->save();
                }
            }
        });
    }

    private function updateProductPrice(int $productId): void
    {
        $product = Product::with('profitability')->find($productId);
        if (!$product) {
            return;
        }

        $product->sale_price = $this->calculateSalePriceForProduct($product);
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
            return (float) ceil(round($newPrice, 4));
        }

        return round($newPrice, $this->getPricePrecision($generalSettings));
    }
}
