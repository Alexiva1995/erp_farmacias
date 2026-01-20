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
        }
        // Removido el filtro de cantidad > 0 para mostrar todos los lotes

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm, $isStrictSearch) {
                if ($isStrictSearch) {
                    // Búsqueda estricta: usar REGEXP con límites de palabra para coincidencias exactas
                    // Esto evita que "loratadina" coincida con "desloratadina"
                    $escapedTerm = preg_quote($searchTerm, '/');
                    // Buscar palabra completa: al inicio del string, al final, o con espacios/caracteres no alfanuméricos alrededor
                    $pattern = "(^|[^a-zA-Z0-9]){$escapedTerm}([^a-zA-Z0-9]|$)";
                    
                    $q->whereRaw("lot_number REGEXP ?", [$pattern])
                        ->orWhereHas('product', function ($productQuery) use ($pattern, $searchTerm) {
                            $productQuery->whereRaw("name REGEXP ?", [$pattern])
                                ->orWhereRaw("active_ingredient REGEXP ?", [$pattern])
                                ->orWhere('barcode', '=', $searchTerm)
                                ->orWhere('id', '=', $searchTerm);
                        });
                } else {
                    // Búsqueda normal: permite coincidencias parciales
                    $words = explode(' ', trim($searchTerm));
                    $q->where(function ($wordClauses) use ($words, $searchTerm) {
                        foreach ($words as $word) {
                            $word = trim($word);
                            if (empty($word)) continue;
                            $wordPattern = "%{$word}%";
                            $wordClauses->where(function ($fieldClauses) use ($wordPattern, $searchTerm) {
                                $fieldClauses->orWhere('lot_number', 'like', $wordPattern)
                                    ->orWhereHas('product', function ($productQuery) use ($wordPattern, $searchTerm) {
                                        $productQuery->where('name', 'like', $wordPattern)
                                            ->orWhere('active_ingredient', 'like', $wordPattern)
                                            ->orWhere('barcode', 'like', $wordPattern)
                                            ->orWhere('id', 'like', $wordPattern)
                                            ->orWhereHas('laboratory', function ($labQuery) use ($wordPattern) {
                                                $labQuery->where('name', 'like', $wordPattern);
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

        // Aplicar ordenamiento
        $sortBy = $request->input('sortBy', 'id');
        $orderBy = $request->input('orderBy', 'desc');
        
        if ($request->has('sortBy') && $request->has('orderBy')) {
            $this->applySorting($query, $sortBy, $orderBy);
        } else {
            // Ordenamiento por defecto: por nombre de producto
            // Verificar si ya hay un join con products para evitar duplicados
            $hasProductsJoin = $this->hasJoin($query, 'products');
            
            if (!$hasProductsJoin) {
                $query->join('products', 'product_lots.product_id', '=', 'products.id');
            }
            $query->orderBy('products.name', 'asc');
        }
        
        // Usar distinct para evitar duplicados cuando hay joins
        // Verificar si hay joins después de aplicar el ordenamiento
        $hasJoins = !empty($query->getQuery()->joins);
        if ($hasJoins) {
            $query->distinct();
        }
        
        return $query;
    }

    public function getProductsWithInconsistentStockQuery(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with(['product.laboratory', 'product.origin', 'supplier'])
            ->where(function ($q) {
                // Mostrar lotes que les falte ubicación o nombre (lot_number)
                $q->where(function ($locationQuery) {
                    $locationQuery->whereNull('product_lots.location')
                        ->orWhere('product_lots.location', '');
                })
                ->orWhere(function ($lotNumberQuery) {
                    $lotNumberQuery->whereNull('product_lots.lot_number')
                        ->orWhere('product_lots.lot_number', '');
                });
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
                // Búsqueda estricta: usar REGEXP con límites de palabra para coincidencias exactas
                $escapedTerm = preg_quote($searchTerm, '/');
                $pattern = "(^|[^a-zA-Z0-9]){$escapedTerm}([^a-zA-Z0-9]|$)";
                
                $q->whereRaw("lot_number REGEXP ?", [$pattern])
                    ->orWhereHas('product', function ($productQuery) use ($pattern, $searchTerm) {
                        $productQuery->whereRaw("name REGEXP ?", [$pattern])
                            ->orWhereRaw("active_ingredient REGEXP ?", [$pattern])
                            ->orWhere('barcode', '=', $searchTerm)
                            ->orWhere('id', '=', $searchTerm);
                    });
            } else {
                // Búsqueda normal: permite coincidencias parciales
                $words = explode(' ', trim($searchTerm));
                $q->where(function ($wordClauses) use ($words) {
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (empty($word)) continue;
                        $wordPattern = "%{$word}%";
                        $wordClauses->where(function ($fieldClauses) use ($wordPattern, $searchTerm) {
                            $fieldClauses->orWhere('lot_number', 'like', $wordPattern)
                                ->orWhereHas('product', function ($productQuery) use ($wordPattern, $searchTerm) {
                                    $productQuery->where('name', 'like', $wordPattern)
                                        ->orWhere('active_ingredient', 'like', $wordPattern)
                                        ->orWhere('barcode', 'like', $wordPattern)
                                        ->orWhere('id', 'like', $wordPattern)
                                        ->orWhereHas('laboratory', function ($labQuery) use ($wordPattern) {
                                            $labQuery->where('name', 'like', $wordPattern);
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
     * Verifica si ya existe un join con una tabla específica
     */
    private function hasJoin($query, $tableName)
    {
        $joins = $query->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            if (isset($join->table) && $join->table === $tableName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Aplica el ordenamiento a la consulta
     */
    private function applySorting($query, $sortBy, $orderBy)
    {
        switch ($sortBy) {
            case 'product.name':
                if (!$this->hasJoin($query, 'products')) {
                    $query->join('products', 'product_lots.product_id', '=', 'products.id');
                }
                $query->orderBy('products.name', $orderBy);
                break;

            case 'supplier.name':
                if (!$this->hasJoin($query, 'suppliers')) {
                    $query->leftJoin('suppliers', 'product_lots.supplier_id', '=', 'suppliers.id');
                }
                $query->orderBy('suppliers.name', $orderBy);
                break;

            case 'laboratory.name':
                if (!$this->hasJoin($query, 'products')) {
                    $query->join('products', 'product_lots.product_id', '=', 'products.id');
                }
                if (!$this->hasJoin($query, 'laboratories')) {
                    $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id');
                }
                $query->orderBy('laboratories.name', $orderBy);
                break;
            
            case 'origin.name':
                if (!$this->hasJoin($query, 'products')) {
                    $query->join('products', 'product_lots.product_id', '=', 'products.id');
                }
                if (!$this->hasJoin($query, 'origins')) {
                    $query->join('origins', 'products.origin_id', '=', 'origins.id');
                }
                $query->orderBy('origins.name', $orderBy);
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
