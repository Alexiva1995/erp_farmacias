<?php


namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface User
{

    public function getAll(): Collection;
}
