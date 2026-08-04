<?php

namespace App\Contracts\Repositories;

use App\Models\CompanyOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function createOfferWithScales(array $data, array $scales): CompanyOffer;
    public function updateOfferWithScales(CompanyOffer $offer, array $data, array $scales): CompanyOffer;
    public function deleteOffer(CompanyOffer $offer): bool;
    public function recalculateStatus(CompanyOffer $offer): array;
}
