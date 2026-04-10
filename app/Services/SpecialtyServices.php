<?php

namespace App\Services;

use App\Contracts\Specialty;
use App\Repository\SpecialtyRepository;
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
