<?php

namespace App\Repositories;

use App\Contracts\Repositories\PrescriptionOfferRepositoryInterface;
use App\Models\Order;
use App\Models\PrescriptionOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PrescriptionOfferRepository implements PrescriptionOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = PrescriptionOffer::query();

        // Subconsulta optimizada en SQL sin N+1 para obtener la suma de ventas
        $query->addSelect([
            'sales_count' => DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.discount_source_id', 'prescription_offers.id')
                ->whereIn('order_details.discount_type', ['prescription', 'recipe'])
                ->where('orders.status', Order::COMPLETED)
                ->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
        ]);

        if (!empty($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('discount_percentage', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['order'] ?? $filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'name', 'discount_percentage', 'start_date', 'end_date', 'is_active', 'created_at', 'sales_count'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function create(array $data): PrescriptionOffer
    {
        return PrescriptionOffer::create($data);
    }

    public function update(PrescriptionOffer $prescriptionOffer, array $data): PrescriptionOffer
    {
        $prescriptionOffer->update($data);
        return $prescriptionOffer;
    }

    public function delete(PrescriptionOffer $prescriptionOffer): bool
    {
        return (bool) $prescriptionOffer->delete();
    }

    public function addProduct(PrescriptionOffer $prescriptionOffer, array $data): array
    {
        return DB::transaction(function () use ($prescriptionOffer, $data) {
            $prescriptionOffer->addProduct($data['product_id'], $data['sale_price'], $data['quantity']);
            $prescriptionOffer->save();

            return [
                'products_with_details' => $prescriptionOffer->products_with_details,
                'total_cost' => $prescriptionOffer->total_cost,
                'total_discount_amount' => $prescriptionOffer->total_discount_amount,
                'final_total_cost' => $prescriptionOffer->final_total_cost,
            ];
        });
    }

    public function updateProductQuantity(PrescriptionOffer $prescriptionOffer, array $data): array
    {
        return DB::transaction(function () use ($prescriptionOffer, $data) {
            $updated = $prescriptionOffer->updateProductQuantity($data['product_id'], $data['quantity']);
            if (!$updated) {
                throw new \Exception('Producto no encontrado en la oferta', 404);
            }
            $prescriptionOffer->save();

            return [
                'products_with_details' => $prescriptionOffer->products_with_details,
                'total_cost' => $prescriptionOffer->total_cost,
                'total_discount_amount' => $prescriptionOffer->total_discount_amount,
                'final_total_cost' => $prescriptionOffer->final_total_cost,
            ];
        });
    }

    public function removeProduct(PrescriptionOffer $prescriptionOffer, int $productId): array
    {
        return DB::transaction(function () use ($prescriptionOffer, $productId) {
            $removed = $prescriptionOffer->removeProduct($productId);
            if (!$removed) {
                throw new \Exception('Producto no encontrado en la oferta', 404);
            }
            $prescriptionOffer->save();

            return [
                'products_with_details' => $prescriptionOffer->products_with_details,
                'total_cost' => $prescriptionOffer->total_cost,
                'total_discount_amount' => $prescriptionOffer->total_discount_amount,
                'final_total_cost' => $prescriptionOffer->final_total_cost,
            ];
        });
    }
}
