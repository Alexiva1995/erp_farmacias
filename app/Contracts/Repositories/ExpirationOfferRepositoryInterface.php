<?php

namespace App\Contracts\Repositories;

use App\Models\ExpirationOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ExpirationOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function isRuleActiveForMonths(int $months, ?int $ignoreId = null): bool;
    public function create(array $data): ExpirationOffer;
    public function update(ExpirationOffer $expirationOffer, array $data): ExpirationOffer;
    public function delete(ExpirationOffer $expirationOffer): bool;
    public function getAvailableProductLots(int $months = 6): Collection;
}
