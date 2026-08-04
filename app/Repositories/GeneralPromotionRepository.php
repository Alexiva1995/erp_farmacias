<?php

namespace App\Repositories;

use App\Contracts\Repositories\GeneralPromotionRepositoryInterface;
use App\Models\GeneralPromotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeneralPromotionRepository implements GeneralPromotionRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = GeneralPromotion::query();

        if (!empty($filters['search_id'])) {
            $query->where('id', $filters['search_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $orderBy = strtolower($filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'type', 'fixed_price', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $orderBy);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function create(array $data): GeneralPromotion
    {
        return GeneralPromotion::create($data);
    }

    public function update(GeneralPromotion $generalPromotion, array $data): GeneralPromotion
    {
        $generalPromotion->update($data);
        return $generalPromotion;
    }

    public function delete(GeneralPromotion $generalPromotion): bool
    {
        return (bool) $generalPromotion->delete();
    }
}
