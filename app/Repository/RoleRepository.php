<?php

namespace App\Repository;

use App\Models\Role;


class RoleRepository
{

  public function list()
  {
    return Role::query()->select(['id', 'name'])->get();
  }
}
