<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\Ecommerce\EcommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EcommerceController extends Controller
{
    protected EcommerceOrderService $orderService;

    public function __construct(EcommerceOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Listar productos públicos con sus variantes para el E-commerce.
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::with(['category'])
            ->where('is_active', true)
            ->where('is_deleted', false);

        // Si se solicita filtrar únicamente favoritos
        if ($request->boolean('favorites_only')) {
            $query->where('is_favorite', true)->latest();
            $products = $query->get(); // Retornamos todos los favoritos para el deslizador
        } else {
            // Catálogo general: obligatoriamente mostrar solo productos con imagen configurada
            $query->whereNotNull('photo_url')->where('photo_url', '!=', '');

            // Filtrar por categoría (slug generado del nombre)
            if ($request->filled('category')) {
                $categorySlug = $request->input('category');
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->whereRaw('LOWER(REPLACE(name, \' \', \'-\')) = ?', [$categorySlug]);
                });
            }

            // Búsqueda por texto (opcional)
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            $query->latest();
            $products = $query->paginate(8); // 8 productos por página para grilla de 4x2
        }

        // Mapear photo_url a image_url para compatibilidad con el frontend
        if ($products instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $products->getCollection()->transform(function ($product) {
                $product->image_url = $product->photo_url;
                return $product;
            });
        } else {
            $products->transform(function ($product) {
                $product->image_url = $product->photo_url;
                return $product;
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * Listar las categorías del e-commerce.
     */
    public function getCategories(): JsonResponse
    {
        // Obtener categorías reales con conteo de productos activos
        $categories = Category::withCount(['products' => function ($q) {
                $q->where('is_active', true)->where('is_deleted', false);
            }])
            ->select('id', 'name')
            ->get()
            ->map(function ($cat) {
                // Generar slug dinámicamente desde el nombre
                $cat->slug = \Illuminate\Support\Str::slug($cat->name);
                $cat->products_count = $cat->products_count;
                return $cat;
            });

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    /**
     * Procesar la compra/orden del e-commerce.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name'    => 'required|string|max:255',
            'customer_email'   => 'nullable|email|max:255',
            'customer_phone'   => 'required|string|max:30',
            'shipping_address' => 'nullable|string|max:500',
            'notes'            => 'nullable|string|max:1000',
            'payment_method'   => 'nullable|string|max:50',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->orderService->createOrder(
                $request->only(['customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'payment_method']),
                $request->input('items')
            );

            return response()->json([
                'success' => true,
                'message' => 'Pedido registrado con éxito.',
                'order_id' => $result['order_id'],
                'total_amount' => $result['total_amount'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Alternar el estado de favorito de un producto.
     */
    public function toggleFavorite(int $id, \App\Services\Ecommerce\EcommerceProductService $productService): JsonResponse
    {
        try {
            $product = $productService->toggleFavorite($id);

            return response()->json([
                'success' => true,
                'message' => 'Estado de favorito actualizado con éxito.',
                'data' => [
                    'id' => $product->id,
                    'is_favorite' => $product->is_favorite,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtener listado de pedidos de e-commerce para el panel administrativo.
     */
    public function getAdminOrders(Request $request): JsonResponse
    {
        $orders = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
            ->select('ecommerce_orders.*', 'users.username as assigned_user')
            ->leftJoin('users', 'users.id', '=', 'ecommerce_orders.user_id')
            ->orderBy('ecommerce_orders.id', 'desc')
            ->get();

        foreach ($orders as $order) {
            $order->items = \Illuminate\Support\Facades\DB::table('ecommerce_order_items')
                ->join('products', 'products.id', '=', 'ecommerce_order_items.product_id')
                ->leftJoin('product_variants', 'product_variants.id', '=', 'ecommerce_order_items.product_variant_id')
                ->select(
                    'ecommerce_order_items.*', 
                    'products.name as product_name', 
                    'product_variants.attribute_value as variant_value'
                )
                ->where('ecommerce_order_id', $order->id)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * Aprobar una orden de e-commerce (cambiar estado a Paid).
     */
    public function approveOrder(int $id): JsonResponse
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update([
                    'status' => 'Paid',
                    'updated_at' => now()
                ]);

            if (!$updated) {
                throw new \Exception("La orden no fue encontrada o ya está en ese estado.");
            }

            return response()->json([
                'success' => true,
                'message' => 'La orden ha sido aprobada con éxito.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancelar una orden de e-commerce (cambiar estado a Cancelled).
     */
    public function cancelOrder(int $id): JsonResponse
    {
        try {
            // Devolver stock
            $items = \Illuminate\Support\Facades\DB::table('ecommerce_order_items')
                ->where('ecommerce_order_id', $id)
                ->get();

            foreach ($items as $item) {
                if (!empty($item->product_variant_id)) {
                    \Illuminate\Support\Facades\DB::table('product_variants')
                        ->where('id', $item->product_variant_id)
                        ->increment('stock', $item->quantity);
                } else {
                    \Illuminate\Support\Facades\DB::table('products')
                        ->where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

            \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update([
                    'status' => 'Cancelled',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'La orden ha sido cancelada con éxito.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
