<?php

namespace App\Services;

use App\Contracts\Location as LocationContract;
use Illuminate\Support\Collection;

class LocationServices
{
    /**
     * Location repository.
     * 
     * @var LocationContract
     */
    protected $locationRepository;

    /**
     * Constructor.
     * 
     * @param LocationContract $locationRepository
     */
    public function __construct(LocationContract $locationRepository)
    {
        $this->locationRepository = $locationRepository;
    }

    /**
     * Get all locations.
     * 
     * @return Collection
     */
    public function getAllLocations(): Collection
    {
        return $this->locationRepository->getAll();
    }

    /**
     * Create a new location.
     * 
     * @param array $data
     * @return \App\Models\Location
     */
    public function createLocation(array $data): \App\Models\Location
    {
        return $this->locationRepository->create($data);
    }

    /**
     * Update an existing location.
     * 
     * @param int $id
     * @param array $data
     * @return \App\Models\Location
     */
    public function updateLocation(int $id, array $data): \App\Models\Location
    {
        $location = $this->locationRepository->find($id);
        
        if (!$location) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Ubicación no encontrada");
        }

        return $this->locationRepository->update($location, $data);
    }

    /**
     * Delete a location.
     * 
     * @param int $id
     * @return bool
     */
    public function deleteLocation(int $id): bool
    {
        $location = $this->locationRepository->find($id);

        if (!$location) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Ubicación no encontrada");
        }

        return $this->locationRepository->delete($location);
    }
}
