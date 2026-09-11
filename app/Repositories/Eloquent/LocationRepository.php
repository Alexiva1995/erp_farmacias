<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Location as LocationContract;
use App\Models\Location;
use Illuminate\Support\Collection;

class LocationRepository implements LocationContract
{
    /**
     * Get all locations.
     * 
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Location::query()
            ->select([
                'locations.id',
                'locations.name',
                'locations.created_at',
                'locations.updated_at',
            ])
            ->leftJoin('product_lots', 'locations.name', '=', 'product_lots.location')
            ->selectRaw('COUNT(DISTINCT product_lots.product_id) as products_count, COALESCE(SUM(product_lots.quantity), 0) as units_count')
            ->groupBy('locations.id', 'locations.name', 'locations.created_at', 'locations.updated_at')
            ->orderByDesc('units_count')
            ->orderBy('locations.name')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?Location
    {
        return Location::find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Location
    {
        return Location::create($data);
    }

    /**
     * @inheritDoc
     */
    public function update(Location $location, array $data): Location
    {
        $location->update($data);
        return $location;
    }

    /**
     * @inheritDoc
     */
    public function delete(Location $location): ?bool
    {
        return $location->delete();
    }
}
