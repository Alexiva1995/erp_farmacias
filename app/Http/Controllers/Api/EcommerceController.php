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

        // Filtrar por categoría (slug generado del nombre)
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                // Comparar slug generado (lowercase, guiones) con el nombre de la categoría
                $q->whereRaw('LOWER(REPLACE(name, \' \', \'-\')) = ?', [$categorySlug]);
            });
        }

        // Búsqueda por texto (opcional)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->latest()->paginate(16);

        // Mapear photo_url a image_url para compatibilidad con el frontend
        $products->getCollection()->transform(function ($product) {
            $product->image_url = $product->photo_url;
            return $product;
        });

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
}
