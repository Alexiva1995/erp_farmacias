<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface Specialty
{
    public function consultAll(): Collection;
}
