<?php

namespace App\Services;

use App\Contracts\Laboratory;
use App\Repository\LaboratoryRepository;
use Illuminate\Database\Eloquent\Collection;

class LaboratoryServices implements Laboratory
{


    public function __construct(
        protected LaboratoryRepository $laboratoryRepository
    ) {}


    public function consultAll(): Collection
    {
        return $this->laboratoryRepository->consultAll();
    }
}
