<?php

namespace App\Services;

use App\Contracts\Repositories\IndividualOfferRepositoryInterface;
use App\Models\IndividualOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use ValidationException;

class IndividualOfferService
{
    public function __construct(
        protected IndividualOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $data): IndividualOffer
    {
        $conflicting = $this->repository->findConflictingOffer(
            (int) $data['product_id'],
            $data['start_date'],
            $data['end_date']
        );

        if ($conflicting) {
            throw new \Exception('Ya existe una oferta activa para este producto en las fechas seleccionadas', 409);
        }

        return $this->repository->create($data);
    }

    public function updateOffer(IndividualOffer $offer, array $data): IndividualOffer
    {
        $productId = $data['product_id'] ?? $offer->product_id;
        $startDate = $data['start_date'] ?? $offer->start_date;
        $endDate = $data['end_date'] ?? $offer->end_date;

        $conflicting = $this->repository->findConflictingOffer(
            (int) $productId,
            $startDate,
            $endDate,
            $offer->id
        );

        if ($conflicting) {
            throw new \Exception('Ya existe otra oferta activa para este producto en las fechas seleccionadas', 409);
        }

        return $this->repository->update($offer, $data);
    }

    public function deleteOffer(IndividualOffer $offer): bool
    {
        return $this->repository->delete($offer);
    }
}
