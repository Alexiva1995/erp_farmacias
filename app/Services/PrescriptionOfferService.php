<?php

namespace App\Services;

use App\Contracts\Repositories\PrescriptionOfferRepositoryInterface;
use App\Models\PrescriptionOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PrescriptionOfferService
{
    public function __construct(
        protected PrescriptionOfferRepositoryInterface $repository
    ) {}

    public function listOffers(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createOffer(array $validatedData): PrescriptionOffer
    {
        return $this->repository->create([
            'name' => $validatedData['name'],
            'discount_percentage' => $validatedData['discount_percentage'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'is_active' => $validatedData['is_active'] ?? true,
        ]);
    }

    public function updateOffer(PrescriptionOffer $prescriptionOffer, array $validatedData): PrescriptionOffer
    {
        return $this->repository->update($prescriptionOffer, $validatedData);
    }

    public function deleteOffer(PrescriptionOffer $prescriptionOffer): bool
    {
        return $this->repository->delete($prescriptionOffer);
    }

    public function addProductToOffer(PrescriptionOffer $prescriptionOffer, array $validatedData): array
    {
        return $this->repository->addProduct($prescriptionOffer, $validatedData);
    }

    public function updateProductQuantity(PrescriptionOffer $prescriptionOffer, array $validatedData): array
    {
        return $this->repository->updateProductQuantity($prescriptionOffer, $validatedData);
    }

    public function removeProductFromOffer(PrescriptionOffer $prescriptionOffer, int $productId): array
    {
        return $this->repository->removeProduct($prescriptionOffer, $productId);
    }
}
