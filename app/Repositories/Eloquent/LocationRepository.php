<?php

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
}
