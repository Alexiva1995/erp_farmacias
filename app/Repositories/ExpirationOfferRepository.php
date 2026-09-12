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

    public function getQualifyingProducts(ExpirationOffer $expirationOffer, array $filters = []): Collection
    {
        $months = (int) $expirationOffer->months_to_expiration;
        $discountPercentage = (float) $expirationOffer->discount_percentage;
        $search = !empty($filters['q']) ? trim($filters['q']) : null;
        $scope = $filters['scope'] ?? 'qualifying';

        $excludedProductIds = DB::table('expiration_offer_excluded_products')
            ->where('expiration_offer_id', $expirationOffer->id)
            ->pluck('product_id')
            ->toArray();

        $query = ProductLot::with(['product.laboratory', 'product.category', 'supplier:id,name'])
            ->where('quantity', '>', 0)
            ->where('expiration_date', '>', now())
            ->whereHas('product', function ($q) use ($search) {
                $q->where(function ($sub) {
                    $sub->whereNull('is_deleted')->orWhere('is_deleted', false);
                });

                if ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhereHas('laboratory', function ($labQ) use ($search) {
                                $labQ->where('name', 'like', "%{$search}%");
                            });
                    });
                }
            });

        if ($scope === 'qualifying') {
            if (!$search) {
                $query->where(function ($q) use ($months, $excludedProductIds) {
                    $q->whereRaw('(TIMESTAMPDIFF(MONTH, CURDATE(), expiration_date) + 1) <= ?', [$months]);
                    if (!empty($excludedProductIds)) {
                        $q->orWhereIn('product_id', $excludedProductIds);
                    }
                });
            }
        } elseif ($scope === 'excluded') {
            $query->whereIn('product_id', $excludedProductIds ?: [0]);
        }

        $lots = $query->orderBy('expiration_date', 'asc')->get();

        return $lots->map(function ($lot) use ($expirationOffer, $discountPercentage, $excludedProductIds) {
            $product = $lot->product;
            $salePrice = $product ? (float) $product->sale_price : 0;
            $discountAmount = round($salePrice * ($discountPercentage / 100), 2);
            $finalPrice = max(0, round($salePrice - $discountAmount, 2));
            $isExcluded = in_array($product?->id, $excludedProductIds, true);

            $now = now();
            $expDate = $lot->expiration_date ? \Carbon\Carbon::parse($lot->expiration_date) : null;
            $daysRemaining = $expDate ? (int) $now->diffInDays($expDate, false) : 0;
            $monthsRemaining = $expDate ? max(0, ceil($daysRemaining / 30)) : 0;

            return [
                'lot_id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'expiration_date' => $expDate ? $expDate->format('d/m/Y') : null,
                'days_remaining' => $daysRemaining,
                'months_remaining' => $monthsRemaining,
                'quantity' => $lot->quantity,
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? 'Sin nombre',
                'barcode' => $product?->barcode ?? '',
                'sku' => $product?->sku ?? '',
                'laboratory_name' => $product?->laboratory?->name ?? '—',
                'category_name' => $product?->category?->name ?? '—',
                'sale_price' => $salePrice,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'final_price' => $finalPrice,
                'is_excluded' => $isExcluded,
                'is_active_in_offer' => !$isExcluded,
            ];
        });
    }

    public function toggleProductExclusion(ExpirationOffer $expirationOffer, int $productId): bool
    {
        $existing = DB::table('expiration_offer_excluded_products')
            ->where('expiration_offer_id', $expirationOffer->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            DB::table('expiration_offer_excluded_products')
                ->where('id', $existing->id)
                ->delete();
            return false;
        } else {
            DB::table('expiration_offer_excluded_products')->insert([
                'expiration_offer_id' => $expirationOffer->id,
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }
    }
}
