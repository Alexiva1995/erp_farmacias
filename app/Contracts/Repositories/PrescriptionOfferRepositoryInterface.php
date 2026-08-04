<?php

namespace App\Contracts\Repositories;

use App\Models\PrescriptionOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PrescriptionOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator;
    public function create(array $data): PrescriptionOffer;
    public function update(PrescriptionOffer $prescriptionOffer, array $data): PrescriptionOffer;
    public function delete(PrescriptionOffer $prescriptionOffer): bool;
    public function addProduct(PrescriptionOffer $prescriptionOffer, array $data): array;
    public function updateProductQuantity(PrescriptionOffer $prescriptionOffer, array $data): array;
    public function removeProduct(PrescriptionOffer $prescriptionOffer, int $productId): array;
}
