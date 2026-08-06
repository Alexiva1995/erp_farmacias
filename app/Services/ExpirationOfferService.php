<?php

namespace App\Services;

use App\Contracts\Repositories\ExpirationOfferRepositoryInterface;
use App\Models\ExpirationOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpirationOfferService
{
    public function __construct(
        protected ExpirationOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $validatedData): ExpirationOffer
    {
        if ($this->repository->isRuleActiveForMonths((int) $validatedData['months_to_expiration'])) {
            throw new \Exception("Ya existe una oferta activa para este periodo de caducidad ({$validatedData['months_to_expiration']} meses).", 422);
        }

        return DB::transaction(function () use ($validatedData) {
            return $this->repository->create($validatedData);
        });
    }

    public function updateOffer(ExpirationOffer $offer, array $validatedData): ExpirationOffer
    {
        $targetMonths = $validatedData['months_to_expiration'] ?? $offer->months_to_expiration;
        $isActive = $validatedData['is_active'] ?? $offer->is_active;

        if ($isActive && $this->repository->isRuleActiveForMonths((int) $targetMonths, $offer->id)) {
            throw new \Exception("Ya existe otra oferta activa para {$targetMonths} meses.", 422);
        }

        return DB::transaction(function () use ($offer, $validatedData) {
            return $this->repository->update($offer, $validatedData);
        });
    }

    public function deleteOffer(ExpirationOffer $offer): bool
    {
        return DB::transaction(function () use ($offer) {
            return $this->repository->delete($offer);
        });
    }

    public function getAvailableProductLots(int $months = 6): Collection
    {
        return $this->repository->getAvailableProductLots($months);
    }
}
