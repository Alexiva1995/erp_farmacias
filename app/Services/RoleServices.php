<?php

namespace App\Services;

use App\Contracts\Role;
use App\Repository\RoleRepository;
use Illuminate\Database\Eloquent\Collection;

class RoleServices implements Role
{
  public function __construct(
    protected RoleRepository $roleRepository
  ) {
  }

  public function list(): Collection
  {
    return $this->roleRepository->list();
  }

}
