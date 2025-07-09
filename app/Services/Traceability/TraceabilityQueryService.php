<?php

namespace App\Services\Traceability;

use App\Models\InventoryMovement;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TraceabilityQueryService
{
    /**
     *
     *
     * @param Request $request
     * @return Builder
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = InventoryMovement::query()->with('user', 'order', 'invoice', 'supplier', 'product');

        if ($request->filled('q')) {
            $searchTerm = "%{$request->input('q')}%";
            $query->whereHas('product', function ($product) use ($request, $searchTerm) {
                $product->where('id', 'like', $searchTerm);
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('movement_date', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('movement_date', '<=', $request->input('endDate'));
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->input('sortBy'), $request->input('orderBy'));
        } else {
            $query->orderBy('movement_date', 'desc');
        }

        return $query;
    }
}
