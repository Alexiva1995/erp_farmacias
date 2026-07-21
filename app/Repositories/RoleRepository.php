<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;


class RoleRepository implements \App\Contracts\Role
{

  public function list(): Collection
  {
    return Role::query()->select(['id', 'name'])->get();
  }
}
