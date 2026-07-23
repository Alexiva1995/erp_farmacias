<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Specialty;
use App\Repositories\SpecialtyRepository;
use Illuminate\Database\Eloquent\Collection;

class SpecialtyServices implements Specialty
{
    public function __construct(
        protected SpecialtyRepository $specialtyRepository,
    ) {}

    public function consultAll(): Collection
    {
        return $this->specialtyRepository->consultAll();
    }
}
