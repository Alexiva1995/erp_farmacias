<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\AutoReplenishmentRepositoryInterface;
use App\Models\AutoReplenishmentConfig;
use Illuminate\Database\Eloquent\Collection;

class AutoReplenishmentRepository implements AutoReplenishmentRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function getAllWithSupplier(): Collection
    {
        return AutoReplenishmentConfig::with('supplier:id,name')
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): AutoReplenishmentConfig
    {
        return AutoReplenishmentConfig::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(AutoReplenishmentConfig $config, array $data): AutoReplenishmentConfig
    {
        $config->update($data);
        return $config->fresh()?->load('supplier:id,name') ?? $config;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(AutoReplenishmentConfig $config): bool
    {
        return (bool) $config->delete();
    }
}
