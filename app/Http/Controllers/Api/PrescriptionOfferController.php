<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionOffer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionOfferController extends Controller
{
    /**
     * Lista de ofertas de recipe.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PrescriptionOffer::query();

            if ($request->filled('id')) {
                $query->where('id', $request->id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('discount_percentage', 'like', "%{$search}%");
                });
            }
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('order', 'desc');

            $allowedSorts = ['id', 'name', 'discount_percentage', 'start_date', 'end_date', 'is_active', 'created_at'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = $request->input('per_page', 10);
            $offers = $query->paginate($perPage);

            $formattedData = $offers->getCollection()->map(function ($offer) {
                $salesCount = (int) \App\Models\OrderDetail::whereIn('discount_type', ['prescription', 'recipe'])
                    ->where('discount_source_id', $offer->id)
                    ->whereHas('order', function ($q) {
                        $q->where('status', \App\Models\Order::COMPLETED);
                    })
                    ->sum('quantity');

                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'discount_percentage' => $offer->discount_percentage,
                    'start_date' => $offer->start_date,
                    'end_date' => $offer->end_date,
                    'is_active' => $offer->is_active,
                    'is_currently_active' => $offer->is_currently_active,
                    'sales_count' => $salesCount,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ];
            });

            $offers->setCollection($formattedData);

            return response()->json([
                'success' => true,
                'data' => $offers->items(),
                'total' => $offers->total(),
                'per_page' => $offers->perPage(),
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ofertas de recetas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Creacion de la nueva oferta.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $offer = PrescriptionOffer::create([
                'name' => $validated['name'],
                'discount_percentage' => $validated['discount_percentage'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta creada exitosamente',
                'data' => $offer
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar oferta especifica.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $offer = PrescriptionOffer::findOrFail($id);

            $salesCount = (int) \App\Models\OrderDetail::whereIn('discount_type', ['prescription', 'recipe'])
                ->where('discount_source_id', $offer->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', \App\Models\Order::COMPLETED);
                })
                ->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'discount_percentage' => $offer->discount_percentage,
                    'start_date' => $offer->start_date,
                    'end_date' => $offer->end_date,
                    'is_active' => $offer->is_active,
                    'is_currently_active' => $offer->is_currently_active,
                    'sales_count' => $salesCount,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualización de una oferta específica.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $offer = PrescriptionOffer::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ]);

            $offer->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta actualizada exitosamente',
                'data' => $offer
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una oferta especifica.
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $offer = PrescriptionOffer::findOrFail($id);
            $offer->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta eliminada exitosamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar producto a una oferta existente
     */
    public function addProductToOffer(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $offer = PrescriptionOffer::findOrFail($id);

            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'sale_price' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:1',
            ]);

            $offer->addProduct(
                $validated['product_id'],
                $validated['sale_price'],
                $validated['quantity']
            );

            $offer->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado a la oferta exitosamente',
                'data' => [
                    'products_with_details' => $offer->products_with_details,
                    'total_cost' => $offer->total_cost,
                    'total_discount_amount' => $offer->total_discount_amount,
                    'final_total_cost' => $offer->final_total_cost,
                ]
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar producto a la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cantidad de un producto en la oferta
     */
    public function updateProductQuantity(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $offer = PrescriptionOffer::findOrFail($id);

            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            $updated = $offer->updateProductQuantity(
                $validated['product_id'],
                $validated['quantity']
            );

            if ($updated) {
                $offer->save();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Cantidad del producto actualizada exitosamente',
                    'data' => [
                        'products_with_details' => $offer->products_with_details,
                        'total_cost' => $offer->total_cost,
                        'total_discount_amount' => $offer->total_discount_amount,
                        'final_total_cost' => $offer->final_total_cost,
                    ]
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado en la oferta'
                ], 404);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cantidad del producto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover producto de una oferta existente
     */
    public function removeProductFromOffer(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $offer = PrescriptionOffer::findOrFail($id);

            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
            ]);

            $removed = $offer->removeProduct($validated['product_id']);

            if ($removed) {
                $offer->save();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Producto removido de la oferta exitosamente',
                    'data' => [
                        'products_with_details' => $offer->products_with_details,
                        'total_cost' => $offer->total_cost,
                        'total_discount_amount' => $offer->total_discount_amount,
                        'final_total_cost' => $offer->final_total_cost,
                    ]
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado en la oferta'
                ], 404);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Oferta de receta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al remover producto de la oferta: ' . $e->getMessage()
            ], 500);
        }
    }
}
