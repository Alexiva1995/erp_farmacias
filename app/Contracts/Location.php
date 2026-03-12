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
}
