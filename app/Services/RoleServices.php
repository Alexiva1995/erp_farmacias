<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Role;
use App\Repositories\RoleRepository;
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
