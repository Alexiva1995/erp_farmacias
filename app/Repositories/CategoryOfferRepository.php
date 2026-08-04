<?php

namespace App\Repositories;

use App\Contracts\Repositories\CategoryOfferRepositoryInterface;
use App\Models\CategoryOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryOfferRepository implements CategoryOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = CategoryOffer::query()
            ->with(['category:id,name']);

        if (!empty($filters['search_id'])) {
            $query->where('category_offers.id', $filters['search_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('category', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('category_offers.is_active', $filters['is_active']);
        }

        $sortBy = $filters['sort_by'] ?? 'category_offers.id';
        $orderBy = strtolower($filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'category.name') {
            $query->join('categories', 'category_offers.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $orderBy)
                ->select('category_offers.*');
        } else {
            $query->orderBy('category_offers.id', $orderBy);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function findConflictingOffer(int $categoryId, string $startDate, string $endDate, ?int $ignoreId = null): ?CategoryOffer
    {
        $query = CategoryOffer::where('category_id', $categoryId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                  });
            });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function create(array $data): CategoryOffer
    {
        return CategoryOffer::create($data);
    }

    public function update(CategoryOffer $categoryOffer, array $data): CategoryOffer
    {
        $categoryOffer->update($data);
        return $categoryOffer;
    }

    public function delete(CategoryOffer $categoryOffer): bool
    {
        return (bool) $categoryOffer->delete();
    }
}
