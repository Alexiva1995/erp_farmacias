<?php

namespace App\Repositories;

use App\Contracts\Repositories\IndividualOfferRepositoryInterface;
use App\Models\IndividualOffer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndividualOfferRepository implements IndividualOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = IndividualOffer::query()
            ->with(['product:id,name,active_ingredient,sale_price,laboratory_id', 'product.laboratory:id,name']);

        // Subconsulta optimizada en SQL puro / Eloquent sin N+1 para calcular la suma de cantidad vendida
        $query->addSelect([
            'sales_count' => DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.product_id', 'individual_offers.product_id')
                ->where('orders.status', Order::COMPLETED)
                ->whereColumn('orders.order_date', '>=', 'individual_offers.start_date')
                ->whereRaw("orders.order_date <= CONCAT(individual_offers.end_date, ' 23:59:59')")
                ->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
        ]);

        if (!empty($filters['search_id'])) {
            $query->where('individual_offers.id', $filters['search_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('active_ingredient', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'individual_offers.id';
        $orderBy = strtolower($filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'product.name' || $sortBy === 'product_display') {
            $query->join('products', 'individual_offers.product_id', '=', 'products.id')
                ->orderBy('products.name', $orderBy)
                ->select('individual_offers.*');
        } else {
            $query->orderBy('individual_offers.id', $orderBy);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function findConflictingOffer(int $productId, string $startDate, string $endDate, ?int $ignoreId = null): ?IndividualOffer
    {
        $query = IndividualOffer::where('product_id', $productId)
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

    public function create(array $data): IndividualOffer
    {
        return IndividualOffer::create($data);
    }

    public function update(IndividualOffer $individualOffer, array $data): IndividualOffer
    {
        $individualOffer->update($data);
        return $individualOffer;
    }

    public function delete(IndividualOffer $individualOffer): bool
    {
        return (bool) $individualOffer->delete();
    }
}
