<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Models\ExpirationOffer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpirationOfferController extends Controller
{
    /**
     * Obtener la lista de ofertas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ExpirationOffer::withCount(['productLots as product_lots_count'])
                ->with(['productLots.product', 'productLots.supplier']);

            // Búsqueda
            if ($request->has('q') && $request->q) {
                $search = $request->q;
                $query->where(function ($q) use ($search) {
                    $q->where('months_to_expiration', 'like', "%{$search}%")
                        ->orWhere('discount_percentage', 'like', "%{$search}%")
                        ->orWhereHas('productLots.product', function ($productQuery) use ($search) {
                            $productQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Filtro por estado
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', $request->is_active);
            }

            // Filtro por meses
            if ($request->has('months') && $request->months) {
                $query->where('months_to_expiration', $request->months);
            }

            // Ordenamiento
            $sortBy = $request->sortBy ?? 'created_at';
            $orderBy = $request->orderBy ?? 'desc';
            $query->orderBy($sortBy, $orderBy);

            // Paginación
            $itemsPerPage = $request->itemsPerPage ?? 10;
            $offers = $query->paginate($itemsPerPage);

            return response()->json([
                'success' => true,
                'data' => $offers->items(),
                'total' => $offers->total(),
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'last_page' => $offers->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ofertas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una Oferta
     */
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'months_to_expiration' => 'required|integer|min:1|max:36',
                'discount_percentage' => 'required|numeric|min:0.01|max:100',
                'is_active' => 'boolean',
                'product_lot_ids' => 'required|array|min:1',
                'product_lot_ids.*' => 'exists:product_lots,id'
            ]);

            // Verificar que los lotes no tengan ofertas activas para los mismos meses
            $existingOffers = ExpirationOffer::where('months_to_expiration', $validated['months_to_expiration'])
                ->where('is_active', true)
                ->whereHas('productLots', function ($query) use ($validated) {
                    $query->whereIn('product_lots.id', $validated['product_lot_ids']);
                })
                ->exists();

            if ($existingOffers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Algunos lotes seleccionados ya tienen ofertas activas para este período'
                ], 422);
            }

            $offer = ExpirationOffer::create($validated);

            // Asociar los lotes de productos
            $offer->productLots()->attach($validated['product_lot_ids']);

            DB::commit();

            // Cargar relaciones para la respuesta
            $offer->load(['productLots.product', 'productLots.supplier']);

            return response()->json([
                'success' => true,
                'message' => 'Oferta creada exitosamente',
                'data' => $offer
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la oferta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una Oferta
     */
    public function update(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();

        try {

            // Validar primero
            $validated = $request->validate([
                'months_to_expiration' => 'sometimes|integer|min:1|max:36',
                'discount_percentage' => 'sometimes|numeric|min:0.01|max:100',
                'is_active' => 'sometimes|boolean',
                'product_lot_ids' => 'sometimes|array|min:1',
                'product_lot_ids.*' => 'exists:product_lots,id'
            ]);

            // Buscar la oferta manualmente
            $expirationOffer = ExpirationOffer::find($id);

            if (!$expirationOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Oferta no encontrada'
                ], 404);
            }

            // Actualizar los datos básicos de la oferta
            if (count($validated) > 0) {
                $expirationOffer->update($validated);
            }

            // Actualizar lotes asociados si se proporcionan
            if (isset($validated['product_lot_ids'])) {
                $expirationOffer->productLots()->sync($validated['product_lot_ids']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta actualizada exitosamente',
                'data' => $expirationOffer->fresh(['productLots.product', 'productLots.supplier'])
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la oferta: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una Oferta
     */

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $expirationOffer = ExpirationOffer::find($id);

            if (!$expirationOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Oferta no encontrada'
                ], 404);
            }

            $expirationOffer->productLots()->detach();
            $expirationOffer->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la oferta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lotes disponibles para ofertas
     */

    public function getAvailableProductLots(Request $request): JsonResponse
    {
        try {
            $months = $request->get('months', 6);

            $availableLots = ProductLot::with(['product', 'supplier'])
                ->where('quantity', '>', 0) // Asegurar stock
                ->where('expiration_date', '>', now()) // Que no estén expirados
                ->whereHas('product', function ($query) {
                    $query->where('is_deleted', false); // Productos no eliminados
                })
                ->whereDoesntHave('expirationOffers', function ($query) {
                    $query->where('is_active', true); // Sin ofertas activas
                })
                ->get()
                ->map(function ($lot) {
                    return [
                        'id' => $lot->id,
                        'lot_number' => $lot->lot_number,
                        'expiration_date' => $lot->expiration_date,
                        'quantity' => $lot->quantity,
                        'product' => $lot->product ? [
                            'id' => $lot->product->id,
                            'name' => $lot->product->name,
                        ] : null,
                        'supplier' => $lot->supplier ? [
                            'id' => $lot->supplier->id,
                            'name' => $lot->supplier->name,
                        ] : null,
                        'display_name' => $lot->product->name .
                            ' - Lote: ' . $lot->lot_number .
                            ' - Exp: ' . $lot->expiration_date->format('d/m/Y') .
                            ' - Stock: ' . $lot->quantity
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $availableLots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lotes disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
