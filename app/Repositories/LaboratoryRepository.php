<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Collection;

class LaboratoryRepository implements \App\Contracts\Laboratory
{

    public function consultAll(): Collection
    {
        return Laboratory::query()->orderBy("name", "ASC")->get();
    }
}
