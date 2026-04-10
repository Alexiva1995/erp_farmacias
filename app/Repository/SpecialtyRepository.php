<?php

namespace App\Repository;

use App\Contracts\Specialty as SpecialtyContract;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Collection;

class SpecialtyRepository implements SpecialtyContract
{
    public function consultAll(): Collection
    {
        return Specialty::query()->orderBy('name', 'ASC')->get();
    }
}
