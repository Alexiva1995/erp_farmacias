<?php

declare(strict_types=1);

namespace App\Services\Restaurant;

use App\Contracts\ModifierGroupServiceInterface;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModifierGroupService implements ModifierGroupServiceInterface
{
    /**
     * Asegura que la columna auto_apply_by_base_product exista en la conexión activa del tenant.
     */
    private function hasAutoApplyColumn(): bool
    {
        $conn = (new ModifierGroup())->getConnection();
        $schema = $conn->getSchemaBuilder();

        if (!$schema->hasTable('modifier_groups')) {
            return false;
        }

        if ($schema->hasColumn('modifier_groups', 'auto_apply_by_base_product')) {
            return true;
        }

        if ($conn->transactionLevel() === 0) {
            try {
                $schema->table('modifier_groups', function (Blueprint $table) {
                    $table->boolean('auto_apply_by_base_product')->default(true)->after('base_product_id');
                });
                return true;
            } catch (\Throwable $e) {
                return $schema->hasColumn('modifier_groups', 'auto_apply_by_base_product');
            }
        }

        return false;
    }
    /**
     * @inheritDoc
     */
    public function getAll(): Collection
    {
        return ModifierGroup::with([
            'baseProduct:id,name,unit_cost,presentation,unit_of_measure',
            'options.product:id,name,unit_cost,presentation,unit_of_measure',
            'dishes:id,name,category_id',
        ])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?ModifierGroup
    {
        return ModifierGroup::with([
            'baseProduct:id,name,unit_cost,presentation,unit_of_measure',
            'options.product:id,name,unit_cost,presentation,unit_of_measure',
            'dishes:id,name,category_id',
        ])->find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): ModifierGroup
    {
        $hasAutoApply = $this->hasAutoApplyColumn();

        return DB::transaction(function () use ($data, $hasAutoApply) {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_product_id' => $data['base_product_id'] ?? null,
                'min_selections' => $data['min_selections'] ?? 0,
                'max_selections' => $data['max_selections'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ];

            if ($hasAutoApply) {
                $attributes['auto_apply_by_base_product'] = array_key_exists('auto_apply_by_base_product', $data)
                    ? (bool) $data['auto_apply_by_base_product']
                    : true;
            }

            $group = ModifierGroup::create($attributes);

            if (isset($data['dish_ids']) && is_array($data['dish_ids'])) {
                $group->dishes()->sync($data['dish_ids']);
            }

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

            return $group->load([
                'baseProduct:id,name,unit_cost,presentation,unit_of_measure',
                'options.product:id,name,unit_cost,presentation,unit_of_measure',
                'dishes:id,name,category_id',
            ]);
        });
    }

    /**
     * @inheritDoc
     */
    public function update(ModifierGroup $group, array $data): ModifierGroup
    {
        $hasAutoApply = $this->hasAutoApplyColumn();

        return DB::transaction(function () use ($group, $data, $hasAutoApply) {
            $attributes = [
                'name' => $data['name'] ?? $group->name,
                'description' => $data['description'] ?? $group->description,
                'base_product_id' => array_key_exists('base_product_id', $data) ? $data['base_product_id'] : $group->base_product_id,
                'min_selections' => $data['min_selections'] ?? $group->min_selections,
                'max_selections' => $data['max_selections'] ?? $group->max_selections,
                'is_active' => $data['is_active'] ?? $group->is_active,
            ];

            if ($hasAutoApply) {
                $attributes['auto_apply_by_base_product'] = array_key_exists('auto_apply_by_base_product', $data)
                    ? (bool) $data['auto_apply_by_base_product']
                    : $group->auto_apply_by_base_product;
            }

            $group->update($attributes);

            if (isset($data['dish_ids']) && is_array($data['dish_ids'])) {
                $group->dishes()->sync($data['dish_ids']);
            }

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

            return $group->load([
                'baseProduct:id,name,unit_cost,presentation,unit_of_measure',
                'options.product:id,name,unit_cost,presentation,unit_of_measure',
                'dishes:id,name,category_id',
            ]);
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
