<?php

namespace App\Services;

use App\Contracts\Repositories\CompanyOfferRepositoryInterface;
use App\Models\CompanyOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyOfferService
{
    public function __construct(
        protected CompanyOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $validatedData): CompanyOffer
    {
        $offerData = [
            'company_id' => $validatedData['company_id'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'is_active' => $validatedData['is_active'] ?? true,
        ];

        return $this->repository->createOfferWithScales($offerData, $validatedData['scales'] ?? []);
    }

    public function updateOffer(CompanyOffer $offer, array $validatedData): CompanyOffer
    {
        $offerData = [
            'company_id' => $validatedData['company_id'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'is_active' => $validatedData['is_active'] ?? $offer->is_active,
        ];

        return $this->repository->updateOfferWithScales($offer, $offerData, $validatedData['scales'] ?? []);
    }

    public function deleteOffer(CompanyOffer $offer): bool
    {
        return $this->repository->deleteOffer($offer);
    }

    public function recalculateOffer(CompanyOffer $offer): array
    {
        return $this->repository->recalculateStatus($offer);
    }
}
