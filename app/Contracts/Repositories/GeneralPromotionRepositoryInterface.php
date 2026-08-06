<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\GeneralPromotion;

interface GeneralPromotionRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function create(array $data): GeneralPromotion;
    public function update(GeneralPromotion $generalPromotion, array $data): GeneralPromotion;
    public function delete(GeneralPromotion $generalPromotion): bool;
}
