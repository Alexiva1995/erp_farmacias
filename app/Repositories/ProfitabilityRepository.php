<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;
use App\Models\CategoryProfitability;
use App\Models\ProductProfitability;
use App\Models\ProfitabilitySettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProfitabilityRepository implements \App\Contracts\Profitability
{
    public function consultOne(): ?Model
    {
        return ProfitabilitySettings::orderBy('created_at', 'desc')->first();
    }

    public function consultById(string $id): ?Model
    {
        return ProductProfitability::query()->where('product_id', '=', $id)->first();
    }

    public function store(array $data): Model
    {
        return ProfitabilitySettings::create($data);
    }

    public function storeProduct(array $data): Model
    {
        return ProductProfitability::create($data);
    }

    public function editProduct(array $data): Model
    {
        ProductProfitability::where('id', '=', $data['id'])->update($data);

        return ProductProfitability::find($data['id']);
    }

    public function edit(array $data): Model
    {
        ProfitabilitySettings::where('id', '=', $data['id'])->update($data);

        return ProfitabilitySettings::find($data['id']);
    }

    public function getCategoriesWithProfitability(): Collection
    {
        return Category::forInventory()
            ->select(['id', 'name'])
            ->with('profitability')
            ->orderBy('name')
            ->get();
    }

    public function upsertCategoryProfitability(array $data): Model
    {
        return CategoryProfitability::updateOrCreate(
            ['category_id' => $data['category_id']],
            [
                'profitability_percentage' => $data['profitability_percentage'] ?? null,
                'shipping_cost' => $data['shipping_cost'] ?? null,
                'packaging_cost' => $data['packaging_cost'] ?? null,
                'expense_margin' => $data['expense_margin'] ?? null,
                'profit_margin' => $data['profit_margin'] ?? null,
                'tax_usa' => $data['tax_usa'] ?? null,
                'status' => $data['status'] ?? 'open',
            ]
        );
    }

    public function deleteCategoryProfitability(int $categoryId): void
    {
        CategoryProfitability::where('category_id', $categoryId)->delete();
    }
}
