<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\ExpiredLog;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;

class InvestmenController extends Controller
{
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm)
                    ->orWhere('id', 'like', $searchTerm);
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
        $query = Product::with([
            'category',
            'laboratory',
            'origin',
            'lots',
            'relatedProducts' => function ($query) {
                $query->with(['laboratory', 'lots']);
            }
        ]);

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
    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->validated();

        if ($request->hasFile('photo_url')) {
            $path = $request->file('photo_url')->store('products', 'public');

            $validatedData['photo_url'] = $path;
        }

        $relatedProductIds = $validatedData['related_product_ids'] ?? [];
        unset($validatedData['related_product_ids']);

        $product = Product::create($validatedData);

        if (!empty($relatedProductIds)) {
            $product->relatedProducts()->sync($relatedProductIds);
        }

        $createdProduct = $product->fresh([
            'category',
            'laboratory',
            'origin',
            'lots',
            'relatedProducts' => fn($q) => $q->with(['laboratory', 'lots'])
        ]);

        $createdProduct->related_products = $createdProduct->relatedProducts;
        unset($createdProduct->relatedProducts);

        return response()->json([
            'message' => 'Producto creado con éxito.',
            'product' => $createdProduct
        ], 201);
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
    public function getSuppliers()
    {
        $origins = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        return response()->json($origins);
    }
    public function getCategories()
    {
        $category = Category::orderBy('name')->get(['id', 'name']);
        return response()->json($category);
    }
    public function updateProducts(UpdateProductRequest $request, Product $product)
    {
        $validatedData = $request->validated();

        if ($request->hasFile('photo_url')) {
            if ($product->photo_url) {
                Storage::disk('public')->delete($product->photo_url);
            }

            $path = $request->file('photo_url')->store('products', 'public');
            $validatedData['photo_url'] = $path;
        }

        $product->update($validatedData);

        if ($request->has('related_product_ids')) {
            $product->relatedProducts()->sync($validatedData['related_product_ids']);
        }

        $updatedProduct = $product->fresh([
            'category',
            'laboratory',
            'origin',
            'lots',
            'relatedProducts' => fn($q) => $q->with(['laboratory', 'lots'])
        ]);

        $updatedProduct->related_products = $updatedProduct->relatedProducts;
        unset($updatedProduct->relatedProducts);

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
            'product' => $updatedProduct
        ], 200);
    }
    public function removeRelatedProduct(Product $product, Product $related_product)
    {
        $product->relatedProducts()->detach($related_product->id);
        return response()->noContent();
    }
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->noContent();
    }
    private function applyProductFiltersToLotQuery(Builder $lotQuery, Request $request): Builder
    {
        if ($request->filled('q')) {
            $searchTerm = "%{$request->q}%";
            $lotQuery->whereHas('product', function ($productQuery) use ($searchTerm) {
                $productQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', value: $searchTerm)
                    ->orWhere('barcode', 'like', $searchTerm);
            });
        }

        return $lotQuery;
    }
    public function getExpirations(Request $request)
    {
        $today = now()->startOfDay();
        $expirationCutoffDate = now()->addMonths(6)->endOfDay();

        $query = ProductLot::with([
            'product' => function ($productQuery) {
                $productQuery->with(['laboratory', 'origin', 'category']);
            }
        ]);
        $query->where('quantity', '>', 0);
        $query->whereBetween('expiration_date', [$today, $expirationCutoffDate]);

        $this->applyProductFiltersToLotQuery($query, $request);

        if ($request->filled('sortBy') && $request->sortBy === 'name') {
            $query->select('lots.*')
                ->join('products', 'lots.product_id', '=', 'products.id')
                ->orderBy('products.name', $request->input('orderBy', 'asc'));
        } elseif ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        } else {
            $query->orderBy('expiration_date', 'asc');
        }

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }
    public function expireLot(ProductLot $lot)
    {
        if ($lot->quantity <= 0) {
            return response()->json(['message' => 'Este lote ya no tiene unidades.'], 400);
        }

        DB::beginTransaction();

        try {
            $quantityToExpire = $lot->quantity;
            $costPerUnit = $lot->cost_price;
            $totalLostValue = $quantityToExpire * $costPerUnit;

            $lot->quantity = 0;
            $lot->save();

            $totalRemainingStock = ProductLot::where('id', '!=', $lot->id)->sum('quantity');
            if ($totalRemainingStock > 0) {
                $costAdjustmentPerUnit = $totalLostValue / $totalRemainingStock;
                ProductLot::where('id', '!=', $lot->id)
                    ->where('quantity', '>', 0)
                    ->update([
                        'cost_price' => $costAdjustmentPerUnit
                    ]);
            }
            ExpiredLog::create([
                'lot_id' => $lot->id,
                'product_id' => $lot->product_id,
                'product_name' => $lot->product->name,
                'lot_number' => $lot->lot_number,
                'expired_quantity' => $quantityToExpire,
                'cost_per_unit' => $costPerUnit,
                'total_lost_value' => $totalLostValue,
            ]);

            DB::commit();

            return response()->json(['message' => 'Lote caducado y costo redistribuido con éxito.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al caducar lote: ' . $e->getMessage());
            return response()->json(['message' => 'Error al procesar la caducidad del lote.'], 500);
        }
    }


    public function getProductAll(Request $request)
    {
        $query = Product::query();

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

}
