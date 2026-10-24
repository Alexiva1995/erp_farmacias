<?php

declare(strict_types=1);

namespace App\Services\Restaurant;

use App\Contracts\KitchenStationServiceInterface;
use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Collection;

class KitchenStationService implements KitchenStationServiceInterface
{
    /**
     * @inheritDoc
     */
    public function getAll(): Collection
    {
        return KitchenStation::withCount('dishes')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?KitchenStation
    {
        return KitchenStation::withCount('dishes')->find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): KitchenStation
    {
        return KitchenStation::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'color' => $data['color'] ?? 'primary',
            'icon' => $data['icon'] ?? 'tabler-chef-hat',
            'printer_ip' => $data['printer_ip'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 1,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function update(KitchenStation $station, array $data): KitchenStation
    {
        $station->update([
            'name' => $data['name'] ?? $station->name,
            'code' => isset($data['code']) ? strtoupper($data['code']) : $station->code,
            'color' => $data['color'] ?? $station->color,
            'icon' => $data['icon'] ?? $station->icon,
            'printer_ip' => array_key_exists('printer_ip', $data) ? $data['printer_ip'] : $station->printer_ip,
            'is_active' => $data['is_active'] ?? $station->is_active,
            'sort_order' => $data['sort_order'] ?? $station->sort_order,
        ]);

        return $station;
    }

    /**
     * @inheritDoc
     */
    public function delete(KitchenStation $station): bool
    {
        return (bool) $station->delete();
    }
}
