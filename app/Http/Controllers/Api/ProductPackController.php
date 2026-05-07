<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductPackController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductPack::query();

            $query->select('product_packs.*');
            $query->selectSub(function($q) {
                $q->selectRaw('count(distinct order_details.order_id)')
                  ->from('order_details')
                  ->join('orders', 'orders.id', '=', 'order_details.order_id')
                  ->whereColumn('order_details.pack_id', 'product_packs.id')
                  ->where('orders.status', 'Completed');
            }, 'sales_count');

            // Búsqueda por ID
            if ($request->has('search_id') && !empty($request->search_id)) {
                $query->where('id', $request->search_id);
            }

            // Búsqueda por nombre
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Filtro por estado
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', $request->is_active);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'id');
            $order = $request->get('order', 'desc');

            $allowedSorts = ['id', 'name', 'total_price', 'max_quantity', 'max_sale_date', 'is_active', 'created_at', 'sales_count'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $order);
            }

            // Paginación
            $perPage = $request->get('per_page', 10);
            $packs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => collect($packs->items())->map(function ($pack) {
                    return $this->formatPackResponse($pack);
                }),
                'total' => $packs->total(),
                'current_page' => $packs->currentPage(),
                'per_page' => $packs->perPage(),
                'last_page' => $packs->lastPage(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los packs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo pack.
     */
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'pack_config' => 'required|array',
                'total_price' => 'required|numeric|min:0',
                'max_quantity' => 'nullable|integer|min:1',
                'max_sale_date' => 'nullable|date|after_or_equal:today',
                'is_active' => 'boolean',
            ], [
                'name.required' => 'El nombre del pack es obligatorio.',
                'pack_config.required' => 'La configuración del pack es obligatoria.',
                'total_price.required' => 'El precio total es obligatorio.',
                'total_price.min' => 'El precio total no puede ser negativo.',
                'max_quantity.min' => 'La cantidad máxima debe ser al menos 1.',
                'max_sale_date.after_or_equal' => 'La fecha máxima de venta debe ser hoy o una fecha posterior.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validar la estructura de pack_config
            $configErrors = $this->validatePackConfig($request->pack_config);
            if (!empty($configErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la configuración del pack',
                    'errors' => $configErrors
                ], 422);
            }

            $pack = ProductPack::create([
                'name' => $request->name,
                'pack_config' => $request->pack_config,
                'total_price' => $request->total_price,
                'max_quantity' => $request->max_quantity,
                'max_sale_date' => $request->max_sale_date,
                'is_active' => $request->is_active ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack creado exitosamente',
                'data' => $this->formatPackResponse($pack)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un pack especifico.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $pack = ProductPack::find($id);

            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPackResponse($pack)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el pack.
     */
    public function update(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pack = ProductPack::find($id);

            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack no encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'pack_config' => 'sometimes|required|array',
                'total_price' => 'sometimes|required|numeric|min:0',
                'max_quantity' => 'nullable|integer|min:1',
                'max_sale_date' => 'nullable|date|after_or_equal:today',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validar pack_config si se está actualizando
            if ($request->has('pack_config')) {
                $configErrors = $this->validatePackConfig($request->pack_config);
                if (!empty($configErrors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error en la configuración del pack',
                        'errors' => $configErrors
                    ], 422);
                }
            }

            $pack->update([
                'name' => $request->name ?? $pack->name,
                'pack_config' => $request->pack_config ?? $pack->pack_config,
                'total_price' => $request->total_price ?? $pack->total_price,
                'max_quantity' => $request->max_quantity ?? $pack->max_quantity,
                'max_sale_date' => $request->max_sale_date ?? $pack->max_sale_date,
                'is_active' => $request->has('is_active') ? $request->is_active : $pack->is_active,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack actualizado exitosamente',
                'data' => $this->formatPackResponse($pack->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar el pack.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pack = ProductPack::find($id);

            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack no encontrado'
                ], 404);
            }

            $pack->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pack: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar la estructura de pack_config
     */
    private function validatePackConfig(array $packConfig): array
    {
        $errors = [];

        if (empty($packConfig)) {
            $errors['pack_config'] = ['El pack debe contener al menos un producto'];
            return $errors;
        }

        foreach ($packConfig as $productId => $config) {
            // Verificar que el producto existe
            $product = \App\Models\Product::find($productId);
            if (!$product) {
                $errors["product_{$productId}"] = ["El producto con ID {$productId} no existe"];
                continue;
            }

            // Validar estructura del config
            if (!is_array($config)) {
                $errors["product_{$productId}"] = ["Configuración inválida para el producto {$productId}"];
                continue;
            }

            // Validar campos requeridos
            if (!isset($config['quantity']) || $config['quantity'] < 1) {
                $errors["product_{$productId}"] = ["La cantidad debe ser al menos 1 para el producto {$product->name}"];
            }

            if (isset($config['discount_percentage']) && ($config['discount_percentage'] < 0 || $config['discount_percentage'] > 100)) {
                $errors["product_{$productId}"] = ["El descuento debe estar entre 0% y 100% para el producto {$product->name}"];
            }

            if (isset($config['sale_price']) && $config['sale_price'] < 0) {
                $errors["product_{$productId}"] = ["El precio de venta no puede ser negativo para el producto {$product->name}"];
            }

            // Validar stock
            if (isset($config['quantity']) && $product->stock < $config['quantity']) {
                $errors["product_{$productId}"] = [
                    "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, Solicitado: {$config['quantity']}"
                ];
            }
        }

        return $errors;
    }

    /**
     * Formatear la respuesta del pack
     */
    private function formatPackResponse(ProductPack $pack): array
    {
        return [
            'id' => $pack->id,
            'name' => $pack->name,
            'pack_config' => $pack->pack_config,
            'products_info' => $pack->products_info,
            'products_count' => $pack->products_count,
            'total_price' => $pack->total_price,
            'max_quantity' => $pack->max_quantity,
            'max_sale_date' => $pack->max_sale_date,
            'is_active' => $pack->is_active,
            'is_available' => $pack->is_available,
            'sales_count' => (int) ($pack->sales_count ?? 0),
            'created_at' => $pack->created_at,
            'updated_at' => $pack->updated_at,
        ];
    }

    /**
     * Cambiar el estado del pack
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $pack = ProductPack::find($id);

            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack no encontrado'
                ], 404);
            }

            $pack->update([
                'is_active' => !$pack->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado del pack actualizado exitosamente',
                'data' => $this->formatPackResponse($pack)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del pack: ' . $e->getMessage()
            ], 500);
        }
    }
}
