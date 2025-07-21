<?php

namespace App\Repository;

use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Collection;

class LaboratoryRepository
{

    public function consultAll(): Collection
    {
        return Laboratory::query()->orderBy("name", "ASC")->get();
    }
}
