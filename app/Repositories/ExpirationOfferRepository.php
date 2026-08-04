<?php

namespace App\Repositories;

use App\Contracts\Repositories\ExpirationOfferRepositoryInterface;
use App\Models\ExpirationOffer;
use App\Models\Order;
use App\Models\ProductLot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpirationOfferRepository implements ExpirationOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = ExpirationOffer::query();

        // Subconsulta optimizada en SQL puro sin N+1 para obtener el conteo de ventas por caducidad
        $query->addSelect([
            'sales_count' => DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.discount_source_id', 'expiration_offers.id')
                ->where('order_details.discount_type', 'expiration')
                ->where('orders.status', Order::COMPLETED)
                ->selectRaw('COALESCE(SUM(order_details.quantity), 0)')
        ]);

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('months_to_expiration', 'like', "%{$search}%")
                    ->orWhere('discount_percentage', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['months'])) {
            $query->where('months_to_expiration', $filters['months']);
        }

        $sortBy = $filters['sortBy'] ?? 'created_at';
        $orderBy = strtolower($filters['orderBy'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        
        $allowedSorts = ['id', 'months_to_expiration', 'discount_percentage', 'is_active', 'created_at', 'sales_count'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $orderBy);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $itemsPerPage = isset($filters['itemsPerPage']) ? (int) $filters['itemsPerPage'] : 10;

        return $query->paginate($itemsPerPage);
    }

    public function isRuleActiveForMonths(int $months, ?int $ignoreId = null): bool
    {
        $query = ExpirationOffer::where('months_to_expiration', $months)
            ->where('is_active', true);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function create(array $data): ExpirationOffer
    {
        return ExpirationOffer::create($data);
    }

    public function update(ExpirationOffer $expirationOffer, array $data): ExpirationOffer
    {
        $expirationOffer->update($data);
        return $expirationOffer;
    }

    public function delete(ExpirationOffer $expirationOffer): bool
    {
        return (bool) $expirationOffer->delete();
    }

    public function getAvailableProductLots(int $months = 6): Collection
    {
        return ProductLot::with(['product:id,name', 'supplier:id,name'])
            ->where('quantity', '>', 0)
            ->where('expiration_date', '>', now())
            ->whereHas('product', function ($query) {
                $query->where('is_deleted', false);
            })
            ->whereDoesntHave('expirationOffers', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'expiration_date' => $lot->expiration_date,
                    'quantity' => $lot->quantity,
                    'product' => $lot->product ? [
                        'id' => $lot->product->id,
                        'name' => $lot->product->name,
                    ] : null,
                    'supplier' => $lot->supplier ? [
                        'id' => $lot->supplier->id,
                        'name' => $lot->supplier->name,
                    ] : null,
                    'display_name' => ($lot->product?->name ?? 'S/N') .
                        ' - Lote: ' . $lot->lot_number .
                        ' - Exp: ' . ($lot->expiration_date ? $lot->expiration_date->format('d/m/Y') : '—') .
                        ' - Stock: ' . $lot->quantity
                ];
            });
    }
}
