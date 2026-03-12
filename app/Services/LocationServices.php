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
}
