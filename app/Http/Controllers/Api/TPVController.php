<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class TPVController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         $products = Product::with([
            'laboratory',
            'origin',
            'relatedProducts' => function ($query) {
                $query->with(['laboratory']);
            }
        ]);

        $this->applyFilters($products, $request);

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $products->orderBy($request->sortBy, $request->orderBy);
        } else {
            $products->orderBy('name', 'asc');
        }

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $products->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }


    private function applyFilters(Builder $products, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $products->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm);
            });
        }

        if ($request->filled('laboratoryId')) {
            $products->where('laboratory_id', $request->laboratoryId);
        }

        if ($request->filled('originId')) {
            $products->where('origin_id', $request->originId);
        }

        if ($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === false) {
            $products->whereDoesntHave('lots', function ($lotQuery) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay());
            });
        } else {
            if (($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === true) || $request->filled('startDate') || $request->filled('endDate')) {
                $products->whereHas('lots', function ($lotQuery) use ($request) {
                    if ($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === true) {
                        $lotQuery->where('expiration_date', '>=', now()->startOfDay());
                    }
                });
            }
        }

        return $products;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
