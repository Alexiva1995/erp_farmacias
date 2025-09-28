<?php


namespace App\Repository;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{

    public function getAll(): Collection
    {
        return User::query()->orderBy("username", "ASC")->get();
    }
}
