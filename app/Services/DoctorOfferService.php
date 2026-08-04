<?php

namespace App\Services;

use App\Contracts\Repositories\DoctorOfferRepositoryInterface;
use App\Models\DoctorOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DoctorOfferService
{
    public function __construct(
        protected DoctorOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $validatedData): DoctorOffer
    {
        return $this->repository->create($validatedData);
    }

    public function updateOffer(DoctorOffer $doctorOffer, array $validatedData): DoctorOffer
    {
        return $this->repository->update($doctorOffer, $validatedData);
    }

    public function deleteOffer(DoctorOffer $doctorOffer): bool
    {
        return $this->repository->delete($doctorOffer);
    }
}
