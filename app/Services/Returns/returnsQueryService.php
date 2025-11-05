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

    private function getBaseQuery(): Builder {
         return ReturnEntry::query()->with([
            'order.client',
            'order.seller',
            'product',
        ]);
    }

    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('order_id', 'asc');
        }

        switch ($sortBy) {
             case 'quantity':
                return $query->orderBy('quantity', $orderBy);
        }

        return $query;
    }

    public function getQueryOrder(Request $request): Builder
    {
        $query = $this->getBaseQuery();
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));
        return $query;
    }

}
