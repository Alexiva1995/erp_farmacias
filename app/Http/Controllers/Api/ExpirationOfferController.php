<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Models\ExpirationOffer;
use App\Http\Requests\Offers\StoreExpirationOfferRequest;
use App\Http\Requests\Offers\UpdateExpirationOfferRequest;
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
            $query = ExpirationOffer::query();

            // Búsqueda
            if ($request->has('q') && $request->q) {
                $search = $request->q;
                $query->where(function ($q) use ($search) {
                    $q->where('months_to_expiration', 'like', "%{$search}%")
                        ->orWhere('discount_percentage', 'like', "%{$search}%");
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

            // Calcular las unidades vendidas para cada oferta de vencimiento
            $offers->getCollection()->transform(function ($offer) {
                $offer->sales_count = (int) \App\Models\OrderDetail::where('discount_type', 'expiration')
                    ->where('discount_source_id', $offer->id)
                    ->whereHas('order', function ($q) {
                        $q->where('status', \App\Models\Order::COMPLETED);
                    })
                    ->sum('quantity');

                return $offer;
            });

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
    public function store(StoreExpirationOfferRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            // Verificar si ya existe una regla activa para estos meses
            $existingOffer = ExpirationOffer::where('months_to_expiration', $validated['months_to_expiration'])
                ->where('is_active', true)
                ->exists();

            if ($existingOffer) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una oferta activa para este periodo de caducidad (' . $validated['months_to_expiration'] . ' meses).'
                ], 422);
            }

            $offer = ExpirationOffer::create($validated);

            DB::commit();

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
    public function update(UpdateExpirationOfferRequest $request, $id): JsonResponse
    {
        DB::beginTransaction();

        try {

            // Validar primero
            $validated = $request->validated();

            // Buscar la oferta manualmente
            $expirationOffer = ExpirationOffer::find($id);

            if (!$expirationOffer) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Oferta no encontrada'
                ], 404);
            }

            // Verificar si los cambios causarían duplicidad de regla activa
            if (
                (isset($validated['months_to_expiration']) || isset($validated['is_active'])) &&
                ($validated['is_active'] ?? $expirationOffer->is_active)
            ) {
                $targetMonths = $validated['months_to_expiration'] ?? $expirationOffer->months_to_expiration;

                $conflict = ExpirationOffer::where('months_to_expiration', $targetMonths)
                    ->where('is_active', true)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($conflict) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe otra oferta activa para ' . $targetMonths . ' meses.'
                    ], 422);
                }
            }

            // Actualizar los datos básicos de la oferta
            if (count($validated) > 0) {
                $expirationOffer->update($validated);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta actualizada exitosamente',
                'data' => $expirationOffer
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
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Oferta no encontrada'
                ], 404);
            }

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
