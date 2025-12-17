<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryOffer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryOfferController extends Controller
{
    /**
     * Mostrar todas las ofertas por categoría con filtros
     */

    public function index(Request $request)
    {
        $query = CategoryOffer::with([
            'category' => function ($query) {
                $query->select('id', 'name');
            }
        ]);
        // Filtro por ID de oferta
        if ($request->has('search_id') && !empty($request->search_id)) {
            $query->where('id', $request->search_id);
        }

        // Filtro por nombre de categoría o ID de categoría
        if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('id', 'like', '%' . $request->search . '%');
            });
        }

        // Filtro por estado activo
        if ($request->has('is_active') && $request->is_active != '' && $request->is_active != null) {
            $query->where('is_active', $request->is_active);
        }

        // Ordenamiento
        if ($request->has('sort_by') && !empty($request->sort_by)) {
            $order = $request->get('order_by', 'asc');

            // Si el ordenamiento es por categoría, ordenar por nombre de la categoría
            if ($request->sort_by === 'category.name') {
                $query->join('categories', 'category_offers.category_id', '=', 'categories.id')
                    ->orderBy('categories.name', $order)
                    ->select('category_offers.*');
            } else {
                $query->orderBy($request->sort_by, $order);
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $categoryOffers = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $categoryOffers,
        ], 200);
    }

    /**
     * Mostrar una oferta por categoría específica
     */
    public function show($id)
    {
        try {
            $categoryOffer = CategoryOffer::with('category')->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $categoryOffer,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Oferta por categoría no encontrada'
            ], 404);
        }
    }

    /**
     * Crear una nueva oferta por categoría
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $category = Category::find($request->category_id['id']);
        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }
        // Verificar que no exista una oferta activa para la misma categoría en las mismas fechas
        $existingOffer = CategoryOffer::where('category_id', $category->id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            })
            ->first();

        if ($existingOffer) {
            return response()->json([
                'status' => false,
                'message' => 'Ya existe una oferta activa para esta categoría en las fechas seleccionadas'
            ], 409);
        }
        $categoryOffer = CategoryOffer::create([
            'category_id' => $category->id,
            'discount_percentage' => $request->discount_percentage,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Oferta por categoría creada correctamente',
            'data' => $categoryOffer->load('category')
        ], 201);
    }

    /**
     * Actualizar una oferta por categoría existente
     */
    public function update(Request $request, $id)
    {
        try {
            $categoryOffer = CategoryOffer::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|required|exists:categories,id',
                'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no exista otra oferta activa para la misma categoría en las mismas fechas
            if ($request->has('category_id') || $request->has('start_date') || $request->has('end_date')) {
                $existingOffer = CategoryOffer::where('category_id', $request->category_id ?? $categoryOffer->category_id)
                    ->where('id', '!=', $id)
                    ->where(function ($query) use ($request, $categoryOffer) {
                        $startDate = $request->start_date ?? $categoryOffer->start_date;
                        $endDate = $request->end_date ?? $categoryOffer->end_date;

                        $query->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q) use ($startDate, $endDate) {
                                $q->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    })
                    ->first();

                if ($existingOffer) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Ya existe otra oferta activa para esta categoría en las fechas seleccionadas'
                    ], 409);
                }
            }

            $categoryOffer->update([
                'category_id' => $request->category_id ?? $categoryOffer->category_id,
                'discount_percentage' => $request->discount_percentage ?? $categoryOffer->discount_percentage,
                'start_date' => $request->start_date ?? $categoryOffer->start_date,
                'end_date' => $request->end_date ?? $categoryOffer->end_date,
                'is_active' => $request->has('is_active') ? $request->is_active : $categoryOffer->is_active,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Oferta por categoría actualizada correctamente',
                'data' => $categoryOffer->load('category')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Oferta por categoría no encontrada'
            ], 404);
        }
    }

    /**
     * Eliminar una oferta por categoría
     */
    public function destroy($id)
    {
        try {
            $categoryOffer = CategoryOffer::findOrFail($id);
            $categoryOffer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Oferta por categoría eliminada correctamente'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Oferta por categoría no encontrada'
            ], 404);
        }
    }
}
