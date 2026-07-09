<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements \App\Contracts\User
{

    public function getAll(): Collection
    {
        return User::query()->orderBy("username", "ASC")->get();
    }
}
