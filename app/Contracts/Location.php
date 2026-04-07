<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface Location
{
    /**
     * Get all locations.
     * 
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Find a location by ID.
     * 
     * @param int $id
     * @return \App\Models\Location|null
     */
    public function find(int $id): ?\App\Models\Location;

    /**
     * Create a new location.
     * 
     * @param array $data
     * @return \App\Models\Location
     */
    public function create(array $data): \App\Models\Location;

    /**
     * Update an existing location.
     * 
     * @param \App\Models\Location $location
     * @param array $data
     * @return \App\Models\Location
     */
    public function update(\App\Models\Location $location, array $data): \App\Models\Location;

    /**
     * Delete a location.
     * 
     * @param \App\Models\Location $location
     * @return bool|null
     */
    public function delete(\App\Models\Location $location): ?bool;
}
