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
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        if ($isRestaurant) {
            $query = \App\Models\Dish::with(['category'])
                ->where('status', 1);

            if ($request->boolean('favorites_only')) {
                // Si no hay favoritos explícitos en platos, mostramos todos los activos
                $dishes = $query->latest()->get();
            } else {

                if ($request->filled('category')) {
                    $categorySlug = $request->input('category');
                    $query->whereHas('category', function ($q) use ($categorySlug) {
                        $q->whereRaw('LOWER(REPLACE(name, \' \', \'-\')) = ?', [$categorySlug]);
                    });
                }

                if ($request->filled('search')) {
                    $search = $request->input('search');
                    $query->where('name', 'like', "%{$search}%");
                }

                $query->latest();
                // Aumentamos a 100 para restaurantes para poder mostrar todo de forma directa
                $dishes = $query->paginate(100);
            }

            // Transformar platos para que tengan el formato compatible con el front-end de productos
            $transform = function ($dish) {
                $dish->sale_price = $dish->designated_price;
                $dish->image_url = $dish->photo_url;
                $dish->variants = [];
                return $dish;
            };

            if ($dishes instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $dishes->getCollection()->transform($transform);
            } else {
                $dishes->transform($transform);
            }

            return response()->json([
                'success' => true,
                'data'    => $dishes,
            ]);
        }

        $query = Product::with(['category', 'variants'])
            ->where('is_active', true)
            ->where('is_deleted', false);

        // Si se solicita filtrar únicamente favoritos
        if ($request->boolean('favorites_only')) {
            $query->where('is_favorite', true)->latest();
            $products = $query->get(); // Retornamos todos los favoritos para el deslizador
        } else {
            // Catálogo general: mostrar todos los productos del catálogo de e-commerce

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
            $productsCollection = $products->getCollection();
        } else {
            $products->transform(function ($product) {
                $product->image_url = $product->photo_url;
                return $product;
            });
            $productsCollection = $products;
        }

        // Aplicar promociones generales activas
        app(\App\Services\Order\OrderActionService::class)->applyGeneralPromotionsToProducts($productsCollection);

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
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        if ($isRestaurant) {
            $categories = Category::withCount(['dishes' => function ($q) {
                    $q->where('status', 1);
                }])
                ->select('id', 'name')
                ->get()
                ->map(function ($cat) {
                    $cat->slug = \Illuminate\Support\Str::slug($cat->name);
                    $cat->products_count = $cat->dishes_count;
                    return $cat;
                });

            return response()->json([
                'success' => true,
                'data'    => $categories,
            ]);
        }

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
     * Crea la ecommerce_order, sube el comprobante y genera la orden TPV inmediatamente.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name'            => 'required|string|max:255',
            'customer_email'           => 'nullable|email|max:255',
            'customer_phone'           => 'required|string|max:30',
            'shipping_address'         => 'nullable|string|max:500',
            'notes'                    => 'nullable|string|max:1000',
            'payment_method'           => 'nullable|string|max:50',
            'payment_currency'         => 'nullable|string|max:10',  // Moneda elegida por el cliente
            'customer_document_type'   => 'nullable|string|max:5',
            'customer_document_number' => 'nullable|string|max:50',
            'payment_proof'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|integer',
            'items.*.variant_id'       => 'nullable|integer',
            'items.*.quantity'         => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // 1. Crear la orden de e-commerce con moneda y monto en moneda del cliente
            $result = $this->orderService->createOrder(
                $request->only([
                    'customer_name', 'customer_email', 'customer_phone',
                    'shipping_address', 'notes', 'payment_method',
                    'payment_currency', 'customer_document_type', 'customer_document_number',
                ]),
                $request->input('items')
            );

            $ecommerceOrderId = $result['order_id'];

            // 2. Guardar comprobante de pago si fue adjuntado
            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = $request->file('payment_proof')
                    ->store('payment_proofs', 'public');

                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $ecommerceOrderId)
                    ->update(['payment_proof_path' => $proofPath]);
            }

            // 3. Crear la orden TPV inmediatamente (status: pending)
            $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                ->where('id', $ecommerceOrderId)
                ->first();

            $tpvOrderId = $this->consolidateOrder($ecommerceOrder);

            // 4. Vincular la orden TPV al registro e-commerce
            if ($tpvOrderId) {
                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $ecommerceOrderId)
                    ->update(['tpv_order_id' => $tpvOrderId]);
            }

            return response()->json([
                'success'          => true,
                'message'          => 'Pedido registrado con éxito.',
                'order_id'         => $ecommerceOrderId,
                'tpv_order_id'     => $tpvOrderId,
                'total_amount'     => $result['total_amount'],
                'currency'         => $result['currency'],
                'total_in_currency' => $result['total_in_currency'],
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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
            ->select('ecommerce_orders.*', 'users.username as assigned_user')
            ->leftJoin('users', 'users.id', '=', 'ecommerce_orders.user_id');

        if ($startDate) {
            $query->where('ecommerce_orders.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('ecommerce_orders.created_at', '<=', $endDate . ' 23:59:59');
        }

        $orders = $query->orderBy('ecommerce_orders.id', 'desc')->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $orderIds = $orders->pluck('id')->toArray();

        // Traer todos los items de golpe en una sola consulta (Evita N+1)
        $items = \Illuminate\Support\Facades\DB::table('ecommerce_order_items')
            ->join('products', 'products.id', '=', 'ecommerce_order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'ecommerce_order_items.product_variant_id')
            ->select(
                'ecommerce_order_items.*', 
                'products.name as product_name', 
                'product_variants.attribute_value as variant_value'
            )
            ->whereIn('ecommerce_order_id', $orderIds)
            ->get()
            ->groupBy('ecommerce_order_id');

        foreach ($orders as $order) {
            $order->items = $items->get($order->id) ?? [];
        }

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * Aprobar una orden de e-commerce (el pago fue confirmado).
     * La orden TPV ya fue creada al checkout — solo se actualiza el estado.
     */
    public function approveOrder(int $id): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->first();

                if (!$ecommerceOrder) {
                    throw new \Exception('La orden no fue encontrada.');
                }

                if ($ecommerceOrder->status !== 'Pending') {
                    throw new \Exception('Solo se pueden aprobar órdenes en estado Pendiente.');
                }

                // Marcar la ecommerce_order como Pagada
                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->update(['status' => 'Paid', 'updated_at' => now()]);

                // Marcar la orden TPV vinculada como completada (venta confirmada)
                if (!empty($ecommerceOrder->tpv_order_id)) {
                    \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                        ->update(['status' => 'Completed']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'La orden ha sido aprobada. Pago confirmado.',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancelar una orden de e-commerce.
     * Devuelve el stock y cancela también la orden TPV vinculada.
     */
    public function cancelOrder(int $id): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->first();

                if (!$ecommerceOrder) {
                    throw new \Exception('La orden no fue encontrada.');
                }

                // Devolver stock de los ítems
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

                // Cancelar la ecommerce_order
                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->update(['status' => 'Cancelled', 'updated_at' => now()]);

                // Cancelar también la orden TPV vinculada
                if (!empty($ecommerceOrder->tpv_order_id)) {
                    \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                        ->update(['status' => 'cancelled']);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'La orden ha sido cancelada y el stock devuelto.',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Marcar pedido como enviado.
     * La orden TPV ya existe — solo actualiza el estado.
     */
    public function shipOrder(int $id): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->first();

                if (!$ecommerceOrder) {
                    throw new \Exception('La orden no fue encontrada.');
                }

                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->update(['status' => 'Shipped', 'updated_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => 'La orden ha sido marcada como enviada.',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Completar el pedido (entregado al cliente). Marca la orden TPV como cerrada.
     */
    public function completeOrder(int $id): JsonResponse
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $ecommerceOrder = \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->first();

                if (!$ecommerceOrder) {
                    throw new \Exception('La orden no existe.');
                }

                if ($ecommerceOrder->status === 'Completed') {
                    throw new \Exception('La orden ya fue completada anteriormente.');
                }

                if ($ecommerceOrder->status === 'Cancelled') {
                    throw new \Exception('Una orden cancelada no se puede completar.');
                }

                // Actualizar ecommerce_order
                \Illuminate\Support\Facades\DB::table('ecommerce_orders')
                    ->where('id', $id)
                    ->update(['status' => 'Completed', 'updated_at' => now()]);

                // Marcar la orden TPV como completada si aún está pendiente
                if (!empty($ecommerceOrder->tpv_order_id)) {
                    \App\Models\Order::where('id', $ecommerceOrder->tpv_order_id)
                        ->whereNotIn('status', ['Completed', 'cancelled'])
                        ->update(['status' => 'Completed']);
                }

                return response()->json([
                    'success'  => true,
                    'message'  => 'La orden ha sido completada con éxito.',
                    'order_id' => $ecommerceOrder->tpv_order_id,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Consolida el pedido e-commerce en la tabla general de órdenes TPV.
     * Se llama UNA única vez al momento del checkout (status: pending).
     * Al aprobar/completar, solo se actualiza el status a 'closed'.
     */
    private function consolidateOrder(object $ecommerceOrder): int
    {
        $id = $ecommerceOrder->id;
        $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

        // Evitar duplicar si ya fue consolidada previamente
        if (!empty($ecommerceOrder->tpv_order_id)) {
            return (int) $ecommerceOrder->tpv_order_id;
        }

        // Fallback: buscar por referencia ECO- por si la columna aún no existía
        $existingOrder = \App\Models\Order::whereJsonContains('payment_methods', ['reference' => 'ECO-' . $id])->first();
        if ($existingOrder) {
            return $existingOrder->id;
        }

        // Resolver cliente
        $client = null;
        if (!empty($ecommerceOrder->customer_document_number)) {
            $client = \App\Models\Client::where('identification', $ecommerceOrder->customer_document_number)
                ->where('identification_type', $ecommerceOrder->customer_document_type ?? 'V-')
                ->first();
        }
        if (!$client && !empty($ecommerceOrder->customer_phone)) {
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
                'identification_type' => $ecommerceOrder->customer_document_type ?? 'V-',
                'identification' => $ecommerceOrder->customer_document_number ?? ('ECO-' . $id),
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
            if ($isRestaurant) {
                $dish = \App\Models\Dish::find($item->product_id);
                $unitCost = $dish ? ($dish->cost_price ?? 0) : 0;
            } else {
                $product = \App\Models\Product::find($item->product_id);
                $unitCost = $product ? ($product->cost ?? 0) : 0;
            }
            $totalCost += $unitCost * $item->quantity;
        }

        // Usar total_in_currency si está disponible, si no calcular desde total_amount
        $currency = strtoupper($ecommerceOrder->currency ?? 'COP');
        $amountInPaymentCurrency = !empty($ecommerceOrder->total_in_currency)
            ? (float) $ecommerceOrder->total_in_currency
            : (float) $ecommerceOrder->total_amount;

        // Mapear método de pago de e-commerce a método TPV
        $mappedMethod    = 'cash_cop';
        $methodNormalized = strtolower(str_replace([' ', '_', '-'], '', $ecommerceOrder->payment_method ?? ''));

        if (in_array($methodNormalized, ['mobilepayment', 'pagomovil'])) {
            $mappedMethod = 'mobile_payment';
        } elseif (in_array($methodNormalized, ['banktransferbs', 'transferenciabs'])) {
            $mappedMethod = 'bank_transfer_bs';
        } elseif (in_array($methodNormalized, ['cashbs', 'contraentregaves'])) {
            $mappedMethod = 'cash_bs';
        } elseif ($methodNormalized === 'binance') {
            $mappedMethod = 'binance';
        } elseif ($methodNormalized === 'paypal') {
            $mappedMethod = 'paypal';
        } elseif (in_array($methodNormalized, ['cashusd', 'contraentregausd'])) {
            $mappedMethod = 'cash_usd';
        } elseif (in_array($methodNormalized, ['banktransfer', 'transferencia'])) {
            $mappedMethod = 'bank_transfer';
        } elseif (in_array($methodNormalized, ['cashcop', 'contraentregacop', 'contraentrega'])) {
            $mappedMethod = 'cash_cop';
        }

        $paymentMethods = [
            [
                'method'    => $mappedMethod,
                'amount'    => round($amountInPaymentCurrency, 2),
                'currency'  => $currency,
                'reference' => 'ECO-' . $id,
            ]
        ];

        // Tasa USD→COP para campos redundantes de la orden
        $rateUsdToCop = 1.0;
        $usdRateObj   = \App\Models\ExchangeRate::where('currency_code', 'USD')->latest()->first();
        if ($usdRateObj && (float) $usdRateObj->rate > 0) {
            $rateUsdToCop = (float) $usdRateObj->rate;
        }
        $totalAmountUsd = $rateUsdToCop > 0 ? (float) $ecommerceOrder->total_amount / $rateUsdToCop : 0;

        // Crear orden principal en la tabla del TPV — status 'pending' hasta que admin apruebe
        $order = \App\Models\Order::create([
            'client_id'       => $client->id,
            'seller_id'       => $userId,
            'cash_closing_id' => $cashClosing->id,
            'total_amount'    => $amountInPaymentCurrency,   // Monto en moneda del cliente
            'currency'        => $currency,                  // Moneda del cliente
            'total_cost'      => $totalCost,
            'taxable_base'    => $amountInPaymentCurrency,
            'money_returns'   => 0.00,
            'order_date'      => now(),
            'status'          => 'pending',                  // Pendiente hasta que el admin apruebe
            'payment_methods' => $paymentMethods,
            'usd_conversion'  => round($rateUsdToCop, 2),
            'total_amount_usd' => round($totalAmountUsd, 2),
        ]);

        // Crear detalles de productos vinculados
        foreach ($items as $item) {
            if ($isRestaurant) {
                $dish = \App\Models\Dish::find($item->product_id);
                $unitCost = $dish ? ($dish->cost_price ?? 0) : 0;
                $productType = 'App\Models\Dish';
            } else {
                $product = \App\Models\Product::find($item->product_id);
                $unitCost = $product ? ($product->cost ?? 0) : 0;
                $productType = 'App\Models\Product';
            }

            \App\Models\OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'unit_cost' => $unitCost,
                'product_type' => $productType,
            ]);
        }

        return $order->id;
    }
}
