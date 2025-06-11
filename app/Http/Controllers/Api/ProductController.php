<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends Controller
{
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm) // CORREGIDO
                    ->orWhere('barcode', 'like', $searchTerm);
            });
        }

        if ($request->filled('laboratoryId')) {
            $query->where('laboratory_id', $request->laboratoryId);
        }

        if ($request->filled('originId')) {
            $query->where('origin_id', $request->originId);
        }

        if ($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === false) {
            $query->whereDoesntHave('lots', function ($lotQuery) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay());
            });
        } else {
            if (($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === true) || $request->filled('startDate') || $request->filled('endDate')) {
                $query->whereHas('lots', function ($lotQuery) use ($request) {
                    if ($request->has('hasStock') && filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) === true) {
                        $lotQuery->where('expiration_date', '>=', now()->startOfDay());
                    }
                    if ($request->filled('startDate')) {
                        $lotQuery->where('expiration_date', '>=', $request->startDate);
                    }
                    if ($request->filled('endDate')) {
                        $lotQuery->where('expiration_date', '<=', $request->endDate);
                    }
                });
            }
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'laboratory', 'origin', 'lots']);

        $this->applyFilters($query, $request);

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        } else {
            $query->orderBy('name', 'asc');
        }

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function export(Request $request)
    {
        $query = Product::with(['laboratory', 'origin', 'lots']);

        $query = $this->applyFilters($query, $request)->orderBy('name', 'asc');

        $format = $request->input('format', 'xlsx');
        $fileName = 'productos-' . now()->format('Y-m-d') . '.' . $format;

        return Excel::download(new ProductsExport($query), $fileName);
    }

    public function getLaboratories()
    {
        $laboratories = Laboratory::orderBy('name')->get(['id', 'name']);
        return response()->json($laboratories);
    }

    public function getOrigins()
    {
        $origins = Origin::orderBy('name')->get(['id', 'name']);
        return response()->json($origins);
    }
}
