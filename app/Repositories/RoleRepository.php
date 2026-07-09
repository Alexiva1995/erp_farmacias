<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Role;


class RoleRepository implements \App\Contracts\Role
{

  public function list()
  {
    return Role::query()->select(['id', 'name'])->get();
  }
}
