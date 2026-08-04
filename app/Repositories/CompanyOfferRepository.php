<?php

namespace App\Repositories;

use App\Contracts\Repositories\CompanyOfferRepositoryInterface;
use App\Models\CompanyOffer;
use App\Models\CompanyOfferScale;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompanyOfferRepository implements CompanyOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = CompanyOffer::query()
            ->with(['company:id,name', 'scales']);

        // Subconsultas SQL optimizadas en una sola consulta para evitar N+1
        $query->addSelect([
            'sales_count' => DB::table('orders')
                ->join('clients', 'orders.client_id', '=', 'clients.id')
                ->whereColumn('clients.company_id', 'company_offers.company_id')
                ->where('orders.status', Order::COMPLETED)
                ->whereColumn('orders.order_date', '>=', 'company_offers.start_date')
                ->whereRaw("orders.order_date <= CONCAT(company_offers.end_date, ' 23:59:59')")
                ->selectRaw('COUNT(orders.id)'),

            'sales_amount' => DB::table('orders')
                ->join('clients', 'orders.client_id', '=', 'clients.id')
                ->whereColumn('clients.company_id', 'company_offers.company_id')
                ->where('orders.status', Order::COMPLETED)
                ->whereColumn('orders.order_date', '>=', 'company_offers.start_date')
                ->whereRaw("orders.order_date <= CONCAT(company_offers.end_date, ' 23:59:59')")
                ->selectRaw('COALESCE(SUM(orders.total_amount_usd), 0)')
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('company_offers.company_id', $search)
                      ->orWhere('company_offers.id', $search);
                }
                $q->orWhereHas('company', function ($companyQuery) use ($search) {
                    $companyQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('company_offers.is_active', $isActive ? 1 : 0);
        }

        $sortBy = $filters['sort_by'] ?? 'company_offers.created_at';
        $orderBy = strtolower($filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'company_id') {
            $query->join('companies', 'company_offers.company_id', '=', 'companies.id')
                ->orderBy('companies.name', $orderBy)
                ->select('company_offers.*');
        } else {
            $allowedSorts = ['id', 'start_date', 'end_date', 'is_active', 'created_at', 'sales_count', 'sales_amount'];
            if (in_array($sortBy, $allowedSorts, true)) {
                $query->orderBy("company_offers.{$sortBy}", $orderBy);
            } else {
                $query->orderBy('company_offers.created_at', 'desc');
            }
        }

        $perPage = isset($filters['items_per_page']) ? (int) $filters['items_per_page'] : 10;
        $page = isset($filters['page']) ? (int) $filters['page'] : 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function createOfferWithScales(array $data, array $scales): CompanyOffer
    {
        return DB::transaction(function () use ($data, $scales) {
            $offer = CompanyOffer::create($data);

            foreach ($scales as $scaleData) {
                CompanyOfferScale::create([
                    'company_offer_id' => $offer->id,
                    'min_amount' => $scaleData['min_amount'],
                    'max_amount' => $scaleData['max_amount'],
                    'discount_percentage' => $scaleData['discount_percentage'],
                ]);
            }

            return $offer->load(['company', 'scales']);
        });
    }

    public function updateOfferWithScales(CompanyOffer $offer, array $data, array $scales): CompanyOffer
    {
        return DB::transaction(function () use ($offer, $data, $scales) {
            $offer->update($data);

            CompanyOfferScale::where('company_offer_id', $offer->id)->delete();

            $scalesToCreate = [];
            foreach ($scales as $scaleData) {
                $scalesToCreate[] = [
                    'company_offer_id' => $offer->id,
                    'min_amount' => $scaleData['min_amount'],
                    'max_amount' => $scaleData['max_amount'],
                    'discount_percentage' => $scaleData['discount_percentage'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($scalesToCreate)) {
                CompanyOfferScale::insert($scalesToCreate);
            }

            return $offer->fresh(['company', 'scales']);
        });
    }

    public function deleteOffer(CompanyOffer $offer): bool
    {
        return DB::transaction(function () use ($offer) {
            CompanyOfferScale::where('company_offer_id', $offer->id)->delete();
            return (bool) $offer->delete();
        });
    }

    public function recalculateStatus(CompanyOffer $offer): array
    {
        $clientIds = DB::table('clients')
            ->where('company_id', $offer->company_id)
            ->pluck('id');

        $totalSales = (float) DB::table('orders')
            ->whereIn('client_id', $clientIds)
            ->where('status', Order::COMPLETED)
            ->whereBetween('created_at', [$offer->start_date . ' 00:00:00', $offer->end_date . ' 23:59:59'])
            ->sum('total_amount_usd');

        $minRequired = (float) ($offer->scales->min('min_amount') ?? 0);
        $newStatus = $totalSales >= $minRequired;

        $offer->update(['is_active' => $newStatus]);

        return [
            'total_sales' => round($totalSales, 2),
            'min_required' => $minRequired,
            'is_active' => $newStatus,
            'offer' => $offer->fresh(['company', 'scales'])
        ];
    }
}
