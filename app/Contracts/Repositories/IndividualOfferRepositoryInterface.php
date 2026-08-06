<?php

namespace App\Contracts\Repositories;

use App\Models\IndividualOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IndividualOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function findConflictingOffer(int $productId, string $startDate, string $endDate, ?int $ignoreId = null): ?IndividualOffer;
    public function create(array $data): IndividualOffer;
    public function update(IndividualOffer $individualOffer, array $data): IndividualOffer;
    public function delete(IndividualOffer $individualOffer): bool;
}
