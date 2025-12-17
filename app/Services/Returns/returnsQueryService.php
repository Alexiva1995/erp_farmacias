<?php

namespace App\Services\Returns;

use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnEntry;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReturnsQueryService
{

    private function getBaseQuery(array $params): Builder
    {
        $query = ReturnEntry::query()->with([
            'order.client',
            'order.seller',
            'product',
        ]);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', function ($subQuery) use ($search) {
                    $subQuery->where('id', '=', $search);
                })
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if (!empty($params['seller'])) {
            $query->whereHas('order', function ($q) use ($params) {
                $q->where('seller_id', $params['seller']);
            });
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['startDate'])) {
            $query->whereDate('created_at', '>=', $params['startDate']);
        }

        if (!empty($params['endDate'])) {
            $query->whereDate('created_at', '<=', $params['endDate']);
        }

        return $query;
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('returns.created_at', 'desc');
        }

        $orderBy = strtolower($orderBy) === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'order_id':
                $query->orderBy('returns.order_id', $orderBy);
                // dd($query->toRawSql());
                break;

            case 'client':
                $query->leftJoin('orders', 'returns.order_id', '=', 'orders.id')
                    ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                    ->orderBy('clients.name', $orderBy)
                    ->select('returns.*');
                break;

            case 'identificacion':
                $query->leftJoin('orders', 'returns.order_id', '=', 'orders.id')
                    ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                    ->orderBy('clients.identification', $orderBy)
                    ->select('returns.*');
                break;

            case 'amount_refunded':
                $query->orderBy('returns.amount_refunded', $orderBy);
                break;

            case 'date':
                $query->orderBy('returns.created_at', $orderBy);
                break;

            case 'status':
                $query->orderBy('returns.status', $orderBy);
                break;

            default:
                $query->orderBy('returns.created_at', 'desc');
                break;
        }

        return $query;
    }

    public function getQueryOrder(Request $request): Builder
    {
        $params = [
            'search' => $request->input('search'),
            'startDate' => $request->input('startDate'),
            'endDate' => $request->input('endDate'),
            'status' => $request->input('status'),
            'seller' => $request->input('seller'),
        ];
        $query = $this->getBaseQuery($params);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));
        return $query;
    }

}
