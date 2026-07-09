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
        return Location::orderBy('name')->get();
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
