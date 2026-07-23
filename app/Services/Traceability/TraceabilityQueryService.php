<?php

declare(strict_types=1);

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

        $query = InventoryMovement::query()
            ->select('inventory_movements.*')
            ->selectRaw('SUM(inventory_movements.quantity) OVER (PARTITION BY inventory_movements.product_id ORDER BY inventory_movements.id ASC) as global_stock_after')
            ->selectRaw('(SUM(inventory_movements.quantity) OVER (PARTITION BY inventory_movements.product_id ORDER BY inventory_movements.id ASC) - inventory_movements.quantity) as global_stock_before')
            ->with([
                'user.employee',
                'order.seller.employee',
                'order',
                'invoice.supplier',
                'supplier',
                'product',
                'dish'
            ]);

        if ($request->filled('q')) {
            $searchTerm = "%{$request->input('q')}%";
            $query->whereHas('product', function ($product) use ($searchTerm) {
                $product->where('id', 'like', $searchTerm)
                    ->orWhere('name', 'like', $searchTerm)
                    ->orWhereHas('laboratory', function ($laboratory) use ($searchTerm) {
                        $laboratory->where('name', 'like', $searchTerm);
                    });
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('movement_date', '>=', $request->input('startDate'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
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
            $sortBy = $request->input('sortBy');
            $orderBy = $request->input('orderBy');

            if ($sortBy === 'reference') {
                $query->orderBy('order_id', $orderBy)
                    ->orderBy('invoice_id', $orderBy)
                    ->orderBy('id', $orderBy);
            } elseif ($sortBy === 'product.name') {
                $query->join('products', 'inventory_movements.product_id', '=', 'products.id')
                    ->select('inventory_movements.*')
                    ->orderBy('products.name', $orderBy);
            } elseif ($sortBy === 'user.email') {
                $query->join('users', 'inventory_movements.user_id', '=', 'users.id')
                    ->select('inventory_movements.*')
                    ->orderBy('users.email', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Ordenar por id de creación del movimiento (más nuevo primero)
            $query->orderBy('inventory_movements.id', 'desc');
        }

        return $query;
    }

    public function getFilteredQueryByPsychotropics(Request $request): Builder
    {

        $hasStock = $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null;


        $query = InventoryMovement::query()->with([
            'user.employee',
            'order.seller.employee',
            'order',
            'invoice.supplier',
            'supplier',
            'product',
            'dish'
        ]);

        if ($request->filled('q')) {
            $searchTerm = "%{$request->input('q')}%";
            $query->whereHas('product', function ($product) use ($searchTerm) {
                $product->where('id', 'like', $searchTerm)
                    ->orWhere('name', 'like', $searchTerm)
                    ->orWhereHas('laboratory', function ($laboratory) use ($searchTerm) {
                        $laboratory->where('name', 'like', $searchTerm);
                    });
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
            $sortBy = $request->input('sortBy');
            $orderBy = $request->input('orderBy');

            if ($sortBy === 'reference') {
                $query->orderBy('order_id', $orderBy)
                    ->orderBy('invoice_id', $orderBy)
                    ->orderBy('id', $orderBy);
            } elseif ($sortBy === 'product.name') {
                $query->join('products', 'inventory_movements.product_id', '=', 'products.id')
                    ->select('inventory_movements.*')
                    ->orderBy('products.name', $orderBy);
            } elseif ($sortBy === 'user.email') {
                $query->join('users', 'inventory_movements.user_id', '=', 'users.id')
                    ->select('inventory_movements.*')
                    ->orderBy('users.email', $orderBy);
            } else {
                $query->orderBy($sortBy, $orderBy);
            }
        } else {
            // Ordenar por id de creación del movimiento (más nuevo primero)
            $query->orderBy('inventory_movements.id', 'desc');
        }

        return $query;
    }
}
