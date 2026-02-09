<?php

namespace App\Services\Returns;

use App\Models\ReturnEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReturnsQueryService
{
    public function getQueryOrder(Request $request): Builder
    {
        $query = ReturnEntry::query()
            ->with(['order.client', 'order.seller', 'product.laboratory']);

        $this->applySearch($query, $request->input('search'));
        $this->applyStatusFilter($query, $request->input('status'));
        $this->applySellerFilter($query, $request->input('seller'));
        $this->applyDateFilter($query, $request->input('startDate'), $request->input('endDate'));
        $this->applySorting(
            $query,
            $request->input('sortBy'),
            $request->input('orderBy', 'desc')
        );

        return $query;
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (empty($search) || !is_string($search)) {
            return;
        }

        $term = trim($search);
        if ($term === '') {
            return;
        }

        $query->where(function ($q) use ($term) {
            if (is_numeric($term)) {
                $q->where('returns.id', (int) $term)
                    ->orWhereHas('order', fn ($o) => $o->where('id', (int) $term));
            }
            $q->orWhereHas('order.client', function ($sub) use ($term) {
                $sub->where('identification', 'like', "%{$term}%")
                    ->orWhereRaw('CONCAT(COALESCE(identification_type,""), COALESCE(identification,"")) LIKE ?', ["%{$term}%"]);
            })
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"));
        });
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if (empty($status)) {
            return;
        }

        if ($status === 'pending' || strtolower($status) === 'null') {
            $query->whereNull('returns.status');
        } else {
            $query->where('returns.status', $status);
        }
    }

    private function applySellerFilter(Builder $query, $seller): void
    {
        if ($seller === null || $seller === '') {
            return;
        }

        $query->whereHas('order', fn ($q) => $q->where('seller_id', $seller));
    }

    private function applyDateFilter(Builder $query, ?string $startDate, ?string $endDate): void
    {
        if (!empty($startDate)) {
            $query->whereDate('return_date', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('return_date', '<=', $endDate);
        }
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): void
    {
        $orderBy = strtolower($orderBy) === 'asc' ? 'asc' : 'desc';

        if (empty($sortBy)) {
            $query->orderBy('returns.id', $orderBy);
            return;
        }

        switch ($sortBy) {
            case 'id':
                $query->orderBy('returns.id', $orderBy);
                break;
            case 'return_date':
                $query->orderBy('return_date', $orderBy);
                break;
            case 'amount_refunded':
                $query->orderBy('amount_refunded', $orderBy);
                break;
            case 'status':
                $query->orderByRaw("CASE WHEN returns.status IS NULL THEN 0 WHEN returns.status = 'Approved' THEN 1 ELSE 2 END {$orderBy}");
                break;
            case 'order.client.name':
            case 'client_name':
                $query->join('orders', 'returns.order_id', '=', 'orders.id')
                    ->join('clients', 'orders.client_id', '=', 'clients.id')
                    ->select('returns.*')
                    ->orderBy('clients.name', $orderBy);
                break;
            case 'product.name':
                $query->join('products', 'returns.product_id', '=', 'products.id')
                    ->select('returns.*')
                    ->orderBy('products.name', $orderBy);
                break;
            default:
                $query->orderBy('returns.id', $orderBy);
        }
    }
}
