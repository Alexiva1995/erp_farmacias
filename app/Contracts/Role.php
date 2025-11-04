<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface Role
{
  public function list(): Collection;
}
