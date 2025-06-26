<?php

namespace App\Contracts;

use App\Contracts\Methods\ConsultAll;
use Illuminate\Database\Eloquent\Collection;

interface Company
{
    public function consultAll(): Collection;
}
