<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Laboratory;
use App\Repositories\LaboratoryRepository;
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
