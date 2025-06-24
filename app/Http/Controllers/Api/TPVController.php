<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class TPVController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         $products = Product::with([
            'category',
            'laboratory',
            'origin',
            'lots',
            'relatedProducts' => function ($query) {
                $query->with(['laboratory']);
            }
        ]);

       // $this->applyFilters($products, $request);

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
