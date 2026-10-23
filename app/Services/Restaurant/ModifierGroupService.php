<?php

declare(strict_types=1);

namespace App\Services\Restaurant;

use App\Contracts\ModifierGroupServiceInterface;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ModifierGroupService implements ModifierGroupServiceInterface
{
    /**
     * @inheritDoc
     */
    public function getAll(): Collection
    {
        return ModifierGroup::with(['options.product:id,name,unit_cost,presentation,unit_of_measure'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?ModifierGroup
    {
        return ModifierGroup::with(['options.product:id,name,unit_cost,presentation,unit_of_measure'])->find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): ModifierGroup
    {
        return DB::transaction(function () use ($data) {
            $group = ModifierGroup::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'min_selections' => $data['min_selections'] ?? 0,
                'max_selections' => $data['max_selections'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $idx => $opt) {
                    $group->options()->create([
                        'name' => $opt['name'],
                        'modifier_type' => $opt['modifier_type'] ?? 'add',
                        'extra_price_usd' => $opt['extra_price_usd'] ?? 0,
                        'product_id' => $opt['product_id'] ?? null,
                        'portion' => $opt['portion'] ?? 0,
                        'sort_order' => $opt['sort_order'] ?? $idx,
                        'is_active' => $opt['is_active'] ?? true,
                    ]);
                }
            }

            return $group->load(['options.product:id,name,unit_cost,presentation,unit_of_measure']);
        });
    }

    /**
     * @inheritDoc
     */
    public function update(ModifierGroup $group, array $data): ModifierGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update([
                'name' => $data['name'] ?? $group->name,
                'description' => $data['description'] ?? $group->description,
                'min_selections' => $data['min_selections'] ?? $group->min_selections,
                'max_selections' => $data['max_selections'] ?? $group->max_selections,
                'is_active' => $data['is_active'] ?? $group->is_active,
            ]);

            if (isset($data['options']) && is_array($data['options'])) {
                $group->options()->delete();
                foreach ($data['options'] as $idx => $opt) {
                    $group->options()->create([
                        'name' => $opt['name'],
                        'modifier_type' => $opt['modifier_type'] ?? 'add',
                        'extra_price_usd' => $opt['extra_price_usd'] ?? 0,
                        'product_id' => $opt['product_id'] ?? null,
                        'portion' => $opt['portion'] ?? 0,
                        'sort_order' => $opt['sort_order'] ?? $idx,
                        'is_active' => $opt['is_active'] ?? true,
                    ]);
                }
            }

            return $group->load(['options.product:id,name,unit_cost,presentation,unit_of_measure']);
        });
    }

    /**
     * @inheritDoc
     */
    public function delete(ModifierGroup $group): bool
    {
        return DB::transaction(function () use ($group) {
            $group->dishes()->detach();
            $group->options()->delete();
            return (bool) $group->delete();
        });
    }
}
