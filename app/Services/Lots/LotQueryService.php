<?php

namespace App\Services\Lots;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Illuminate\Http\Request;

class LotQueryService
{
    public function getFilteredQuery(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with(['product.laboratory', 'supplier','product.origin']);

        $isStrictSearch = filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN);

        if ($request->has('productId') && !empty($request->productId)) {
            $query->where('product_id', $request->productId);
        } else {
            $query->where('quantity', '>', 0);
        }

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    $q->where('lot_number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                            $productQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('active_ingredient', 'like', "%{$searchTerm}%")
                                ->orWhere('barcode', 'like', $searchTerm)
                                ->orWhere('id', $searchTerm);
                        });
                }else{
                    $words = explode(' ', $searchTerm);
                    $q->where(function ($wordClauses) use ($words) {
                        foreach ($words as $word) {
                            $word = trim($word);
                            if (empty($word)) continue;
                            $wordClauses->where(function ($fieldClauses) use ($word) {
                                $fieldClauses->orWhere('lot_number', 'like', "%{$word}%")
                                    ->orWhereHas('product', function ($productQuery) use ($word) {
                                        $productQuery->where('name', 'like', "%{$word}%")
                                            ->orWhere('active_ingredient', 'like', "%{$word}%")
                                            ->orWhereHas('laboratory', function ($labQuery) use ($word) {
                                                $labQuery->where('name', 'like', "%{$word}%");
                                            });
                                    });
                            });
                        }
                    });
                }

                /*$q->where('lot_number', 'like', "%{$searchTerm}%")
                    ->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('active_ingredient', 'like', "%{$searchTerm}%");
                    });*/
            });
        }

        if ($request->has('laboratoryId') && !empty($request->laboratoryId)) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('laboratory_id', $request->laboratoryId);
            });
        }

        if ($request->has('originId') && !empty($request->originId)) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('origin_id', $request->originId);
            });
        }

        if ($request->has('hasStock') && $request->hasStock !== null) {
            if ($request->hasStock == 'true') {
                $query->where('quantity', '>', 0);
            } else {
                $query->where('quantity', '<=', 0);
            }
        }

        if ($request->has('startDate') && !empty($request->startDate)) {
            $query->whereDate('expiration_date', '>=', $request->startDate);
        }

        if ($request->has('endDate') && !empty($request->endDate)) {
            $query->whereDate('expiration_date', '<=', $request->endDate);
        }

        if ($request->has('sortBy') && $request->has('orderBy') && $request->sortBy !== 'id') {
            $this->applySorting($query, $request->sortBy, $request->orderBy);
        } else {
            //$query->join('products', 'product_lots.product_id', '=', 'products.id')
                //->orderBy('products.name', 'asc');
            if (!in_array($request->sortBy, ['product.name', 'laboratory.name', 'origin.name'])) {
                $query->join('products', 'product_lots.product_id', '=', 'products.id');
            }
            $query->orderBy('products.name', 'asc');
        }
        return $query;
    }

    public function getProductsWithInconsistentStockQuery(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with(['product.laboratory', 'product.origin'])
            ->whereHas('product', function ($productQuery) {
                $productQuery->whereRaw('
                    stock != (
                        SELECT COALESCE(SUM(quantity), 0) 
                        FROM product_lots 
                        WHERE product_id = products.id
                    )
                ');
            });

        $isStrictSearch = filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN);

        /*if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('lot_number', 'like', "%{$searchTerm}%")
                    ->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }*/

    if ($request->has('search') && !empty($request->search)) {
        $searchTerm = $request->search;
        
        $query->where(function ($q) use ($searchTerm, $isStrictSearch) {
            if ($isStrictSearch) {
                $q->where('lot_number', 'like', "%{$searchTerm}%")
                    ->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('active_ingredient', 'like', "%{$searchTerm}%") 
                            ->orWhere('barcode', 'like', $searchTerm)
                            ->orWhere('id', $searchTerm);
                    });
            } else {

                $words = explode(' ', $searchTerm);
                $q->where(function ($wordClauses) use ($words) {
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (empty($word)) continue;
                        $wordClauses->where(function ($fieldClauses) use ($word) {
                            $fieldClauses->orWhere('lot_number', 'like', "%{$word}%")
                                ->orWhereHas('product', function ($productQuery) use ($word) {
                                    $productQuery->where('name', 'like', "%{$word}%")
                                        ->orWhere('active_ingredient', 'like', "%{$word}%")
                                        ->orWhereHas('laboratory', function ($labQuery) use ($word) {
                                            $labQuery->where('name', 'like', "%{$word}%");
                                        });
                                });
                        });
                    }
                });
            }
        });
    }

        if ($request->has('laboratoryId') && !empty($request->laboratoryId)) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('laboratory_id', $request->laboratoryId);
            });
        }

         if ($request->has('originId') && !empty($request->originId)) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('origin_id', $request->originId);
            });
        }

        if ($request->has('startDate') && !empty($request->startDate)) {
            $query->whereDate('expiration_date', '>=', $request->startDate);
        }

        if ($request->has('endDate') && !empty($request->endDate)) {
            $query->whereDate('expiration_date', '<=', $request->endDate);
        }

        if ($request->has('sortBy') && $request->has('orderBy')) {
            $this->applySorting($query, $request->sortBy, $request->orderBy);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        return $query;
    }

    public function getProductsWithoutLot()
    {
        return Product::with('laboratory','origin')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAvailableSuppliers()
    {
        return Supplier::select('id', 'name', 'name')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Aplica el ordenamiento a la consulta
     */
    private function applySorting($query, $sortBy, $orderBy)
    {
        switch ($sortBy) {
            case 'product.name':
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.name', $orderBy);
                break;

            case 'supplier.name':
                $query->leftJoin('suppliers', 'product_lots.supplier_id', '=', 'suppliers.id')
                    ->orderBy('suppliers.name', $orderBy);
                break;

            case 'laboratory.name':
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);
                break;
            
            case 'origin.name':
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->join('origins', 'products.origin_id', '=', 'origins.id')
                    ->orderBy('origins.name', $orderBy);
                break;

            case 'quantity':
                $query->orderBy('quantity', $orderBy);
                break;

            case 'expiration_date':
                $query->orderBy('expiration_date', $orderBy);
                break;

            case 'created_at':
                $query->orderBy('created_at', $orderBy);
                break;

            case 'unit_cost':
                $query->orderBy('unit_cost', $orderBy);
                break;

            default:
                if (in_array($sortBy, ['id', 'lot_number', 'location'])) {
                    $query->orderBy($sortBy, $orderBy);
                } else {
                    $query->orderBy('created_at', 'desc');
                }
                break;
        }
    }
}
