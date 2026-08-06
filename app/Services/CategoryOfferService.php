<?php

namespace App\Services;

use App\Contracts\Repositories\CategoryOfferRepositoryInterface;
use App\Models\CategoryOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryOfferService
{
    public function __construct(
        protected CategoryOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $data): CategoryOffer
    {
        $categoryId = is_array($data['category_id'] ?? null)
            ? ($data['category_id']['id'] ?? null)
            : ($data['category_id'] ?? null);

        $data['category_id'] = $categoryId;

        $conflicting = $this->repository->findConflictingOffer(
            (int) $data['category_id'],
            $data['start_date'],
            $data['end_date']
        );

        if ($conflicting) {
            throw new \Exception('Ya existe una oferta activa para esta categoría en las fechas seleccionadas', 409);
        }

        return $this->repository->create($data);
    }

    public function updateOffer(CategoryOffer $offer, array $data): CategoryOffer
    {
        $categoryId = isset($data['category_id'])
            ? (is_array($data['category_id']) ? ($data['category_id']['id'] ?? $offer->category_id) : $data['category_id'])
            : $offer->category_id;

        $startDate = $data['start_date'] ?? $offer->start_date;
        $endDate = $data['end_date'] ?? $offer->end_date;

        $data['category_id'] = $categoryId;

        $conflicting = $this->repository->findConflictingOffer(
            (int) $categoryId,
            $startDate,
            $endDate,
            $offer->id
        );

        if ($conflicting) {
            throw new \Exception('Ya existe otra oferta activa para esta categoría en las fechas seleccionadas', 409);
        }

        return $this->repository->update($offer, $data);
    }

    public function deleteOffer(CategoryOffer $offer): bool
    {
        return $this->repository->delete($offer);
    }
}
