<?php


namespace App\Services;

use App\Contracts\User;
use App\Repository\UserRepository;
use Illuminate\Database\Eloquent\Collection;

class UserServices implements User
{

    public function __construct(
        protected UserRepository $userRepository
    ) {}

    public function getAll(): Collection
    {
        return $this->userRepository->getAll();
    }
}
