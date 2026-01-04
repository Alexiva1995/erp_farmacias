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

        $query = InventoryMovement::query()->with([
            'user', 
            'order', 
            'invoice.supplier',
            'supplier', 
            'product'
        ]);

        if ($request->filled('q')) {
            $searchTerm = "%{$request->input('q')}%";
            $query->whereHas('product', function ($product) use ($request, $searchTerm) {
                $product->where('id', 'like', $searchTerm);
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('movement_date', '>=', $request->input('startDate'));
        }

        if ($request->filled('is_psychotropic')) {
            $query->whereHas('product', function ($product) use ($request) {
                $product->where('psychotropic', "=", $request->is_psychotropic);
            });
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

    public function getFilteredQueryByPsychotropics(Request $request): Builder
    {

        $hasStock = $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null;


        $query = InventoryMovement::query()->with([
            'user', 
            'order', 
            'invoice.supplier',
            'supplier', 
            'product'
        ]);

        if ($request->filled('q')) {
            $searchTerm = "%{$request->input('q')}%";
            $query->whereHas('product', function ($product) use ($request, $searchTerm) {
                // $product->where('id', 'like', $searchTerm);
                $product->where('name', 'like', $searchTerm);
            });
        }

        if ($request->filled("laboratoryId")) {
            $query->whereHas('product', function ($productQuery) use ($request, $hasStock) {
                $productQuery->where('laboratory_id', $request->laboratoryId);
            });
        }

        $query->whereHas('product', function ($product) use ($request) {
            $product->where('psychotropic', "=", 1);
        });

        if ($hasStock === false) {
            $query->whereDoesntHave('product.lots', function ($lotQuery) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay())
                    ->where('quantity', '>', 0);
            });
        } elseif ($hasStock === true || $request->filled("startDate") || $request->filled("endDate")) {
            $query->whereHas('product.lots', function ($lotQuery) use ($request, $hasStock) {
                $lotQuery->where('quantity', '>', 0);

                if ($hasStock === true) {
                    $lotQuery->where('expiration_date', '>=', now()->startOfDay());
                }
                if (!empty($request->startDate)) {
                    $lotQuery->where('expiration_date', '>=', $request->startDate);
                }
                if (!empty($request->endDate)) {
                    $lotQuery->where('expiration_date', '<=', $request->endDate);
                }
            });
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->input('sortBy'), $request->input('orderBy'));
        } else {
            $query->orderBy('movement_date', 'desc');
        }

        return $query;
    }
}
