<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProductPackRepositoryInterface;
use App\Models\Product;
use App\Models\ProductPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductPackRepository implements ProductPackRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = ProductPack::query();

        $query->select('product_packs.*');
        $query->selectSub(function ($q) {
            $q->selectRaw('count(distinct order_details.order_id)')
              ->from('order_details')
              ->join('orders', 'orders.id', '=', 'order_details.order_id')
              ->whereColumn('order_details.pack_id', 'product_packs.id')
              ->where('orders.status', 'Completed');
        }, 'sales_count');

        if (!empty($filters['search_id'])) {
            $query->where('id', $filters['search_id']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $order = strtolower($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'name', 'total_price', 'max_quantity', 'max_sale_date', 'is_active', 'created_at', 'sales_count'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function validatePackConfigProducts(array $productIds): Collection
    {
        return Product::whereIn('id', $productIds)->get()->keyBy('id');
    }

    public function create(array $data): ProductPack
    {
        return ProductPack::create($data);
    }

    public function update(ProductPack $pack, array $data): ProductPack
    {
        $pack->update($data);
        return $pack;
    }

    public function delete(ProductPack $pack): bool
    {
        return (bool) $pack->delete();
    }
}
