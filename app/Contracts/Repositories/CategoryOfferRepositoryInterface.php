<?php

namespace App\Contracts\Repositories;

use App\Models\CategoryOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function findConflictingOffer(int $categoryId, string $startDate, string $endDate, ?int $ignoreId = null): ?CategoryOffer;
    public function create(array $data): CategoryOffer;
    public function update(CategoryOffer $categoryOffer, array $data): CategoryOffer;
    public function delete(CategoryOffer $categoryOffer): bool;
}
