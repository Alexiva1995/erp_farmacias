<?php

namespace App\Services;

use App\Contracts\Repositories\GeneralPromotionRepositoryInterface;
use App\Models\GeneralPromotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeneralPromotionService
{
    public function __construct(
        protected GeneralPromotionRepositoryInterface $repository
    ) {}

    public function listPromotions(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createPromotion(array $data): GeneralPromotion
    {
        return $this->repository->create($data);
    }

    public function updatePromotion(GeneralPromotion $promotion, array $data): GeneralPromotion
    {
        return $this->repository->update($promotion, $data);
    }

    public function deletePromotion(GeneralPromotion $promotion): bool
    {
        return $this->repository->delete($promotion);
    }
}
