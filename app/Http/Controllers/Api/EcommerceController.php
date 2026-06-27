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
        $query = Product::with(['category', 'variants'])
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

    /**
     * Marcar pedido como enviado.
     */
    public function shipOrder(int $id): JsonResponse
    {
        try {
            $updated = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                ->where('id', $id)
                ->update([
                    'status' => 'Shipped',
                    'updated_at' => now()
                ]);

            if (!$updated) {
                throw new \Exception("La orden no fue encontrada o ya está en ese estado.");
            }

            return response()->json([
                'success' => true,
                'message' => 'La orden ha sido marcada como enviada con éxito.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Completar pedido y consolidarlo en el sistema de facturación/caja.
     */
    public function completeOrder(int $id): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->first();

                if (!$ecommerceOrder) {
                    throw new \Exception("La orden no existe.");
                }

                if ($ecommerceOrder->status === 'Completed') {
                    throw new \Exception("La orden ya fue completada anteriormente.");
                }

                if ($ecommerceOrder->status === 'Cancelled') {
                    throw new \Exception("Una orden cancelada no se puede completar.");
                }

                // Resolver cliente
                $client = null;
                if (!empty($ecommerceOrder->customer_phone)) {
                    $client = \App\Models\Client::where('phone', $ecommerceOrder->customer_phone)->first();
                }
                if (!$client && !empty($ecommerceOrder->customer_email)) {
                    $client = \App\Models\Client::where('email', $ecommerceOrder->customer_email)->first();
                }
                if (!$client) {
                    $client = \App\Models\Client::create([
                        'name' => $ecommerceOrder->customer_name,
                        'phone' => $ecommerceOrder->customer_phone ?? 'N/A',
                        'email' => $ecommerceOrder->customer_email ?? null,
                        'identification_type' => 'V',
                        'identification' => 'ECO-' . $ecommerceOrder->id,
                    ]);
                }

                // Resolver usuario / cajero
                $userId = $ecommerceOrder->user_id ?? \Illuminate\Support\Facades\Auth::id();
                if (!$userId) {
                    $tiendaUser = \Illuminate\Support\Facades\DB::table('users')->where('username', 'tienda')->first()
                        ?? \Illuminate\Support\Facades\DB::table('users')->first();
                    $userId = $tiendaUser ? $tiendaUser->id : null;
                }

                // Abrir o buscar caja
                $cashClosing = \App\Models\CashClosing::where('seller_id', $userId)
                    ->where('status', \App\Models\CashClosing::OPEN)
                    ->first();

                if (!$cashClosing) {
                    $cashClosing = \App\Models\CashClosing::create([
                        'seller_id' => $userId,
                        'status' => \App\Models\CashClosing::OPEN,
                        'opening_date' => now(),
                    ]);
                }

                // Obtener ítems
                $items = \Illuminate\Support\Facades\DB::table('ecommerce_order_items')
                    ->where('ecommerce_order_id', $id)
                    ->get();

                $totalCost = 0;
                foreach ($items as $item) {
                    $product = \App\Models\Product::find($item->product_id);
                    $unitCost = $product ? ($product->cost ?? 0) : 0;
                    $totalCost += $unitCost * $item->quantity;
                }

                $paymentMethods = [
                    [
                        'method' => $ecommerceOrder->payment_method ?? 'Transferencia',
                        'amount' => (float)$ecommerceOrder->total_amount,
                        'reference' => 'ECO-' . $id
                    ]
                ];

                // Crear orden principal
                $order = \App\Models\Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $userId,
                    'cash_closing_id' => $cashClosing->id,
                    'total_amount' => $ecommerceOrder->total_amount,
                    'currency' => 'COP',
                    'total_cost' => $totalCost,
                    'taxable_base' => $ecommerceOrder->total_amount,
                    'order_date' => now(),
                    'status' => 'closed',
                    'payment_methods' => $paymentMethods,
                ]);

                // Crear detalles
                foreach ($items as $item) {
                    $product = \App\Models\Product::find($item->product_id);
                    $unitCost = $product ? ($product->cost ?? 0) : 0;

                    \App\Models\OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'unit_cost' => $unitCost,
                        'product_type' => 'App\Models\Product',
                    ]);
                }

                // Actualizar ecommerce order
                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->update([
                        'status' => 'Completed',
                        'updated_at' => now()
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'La orden ha sido completada y consolidada en ventas con éxito.',
                    'order_id' => $order->id
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
