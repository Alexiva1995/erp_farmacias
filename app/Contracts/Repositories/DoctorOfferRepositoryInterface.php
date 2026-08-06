<?php

namespace App\Contracts\Repositories;

use App\Models\DoctorOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DoctorOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function create(array $data): DoctorOffer;
    public function update(DoctorOffer $doctorOffer, array $data): DoctorOffer;
    public function delete(DoctorOffer $doctorOffer): bool;
}
