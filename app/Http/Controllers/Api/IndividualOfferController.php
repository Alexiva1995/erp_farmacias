<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndividualOffer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class IndividualOfferController extends Controller
{
    //
    /** Funcion para mostrar todas las ofertas individuales almacenadas */
    public function index(Request $request)
    {
        $query = IndividualOffer::with(['product' => function ($query) {
            $query->select('id', 'name', 'active_ingredient', 'sale_price');
        }]);

        // Filtro por ID de oferta
        if ($request->has('search_id') && !empty($request->search_id)) {
            $query->where('id', $request->search_id);
        }

        // Filtro por nombre de producto o ID de producto
        if ($request->has('search') && !empty($request->search)) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('active_ingredient', 'like', '%' . $request->search . '%')
                    ->orWhere('id', 'like', '%' . $request->search . '%'); // Buscar también por ID de producto
            });
        }

        // Ordenamiento
        if ($request->has('sort_by') && !empty($request->sort_by)) {
            $order = $request->get('order_by', 'asc');

            // Si el ordenamiento es por producto, ordenar por nombre del producto
            if ($request->sort_by === 'product.name' || $request->sort_by === 'product_display') {
                $query->join('products', 'individual_offers.product_id', '=', 'products.id')
                    ->orderBy('products.name', $order)
                    ->select('individual_offers.*');
            } else {
                $query->orderBy($request->sort_by, $order);
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $indvOffer = $query->paginate($perPage);

        // Calcular las ventas totales durante la vigencia de la oferta
        $indvOffer->getCollection()->transform(function ($offer) {
            $offer->sales_count = (int) \App\Models\OrderDetail::where('product_id', $offer->product_id)
                ->whereHas('order', function ($q) use ($offer) {
                    $q->where('status', \App\Models\Order::COMPLETED)
                      ->where('order_date', '>=', $offer->start_date)
                      ->where('order_date', '<=', $offer->end_date . ' 23:59:59');
                })
                ->sum('quantity');

            return $offer;
        });

        return response()->json([
            'status' => true,
            'data' => $indvOffer,
        ], 200);
    }

    /** Creacion de una nueva oferta individual */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'discount_percent' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar que no exista una oferta activa para el mismo producto
        $existingOffer = IndividualOffer::where('product_id', $request->product_id)
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
                'message' => 'Ya existe una oferta activa para este producto en las fechas seleccionadas'
            ], 409);
        }
        $indvOffer = IndividualOffer::create($request->all());
        return response()->json([
            'status' => true,
            'message' => 'Offerta individual creada correctamente',
            'data' => $indvOffer
        ], 201);
    }

    public function update(Request $request, $id)
    {
        try {
            $indvOffer = IndividualOffer::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'product_id' => 'sometimes|required|exists:products,id',
                'discount_percent' => 'sometimes|required|numeric|min:0|max:100',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no exista otra oferta activa para el mismo producto en las mismas fechas
            if ($request->has('product_id') || $request->has('start_date') || $request->has('end_date')) {
                $existingOffer = IndividualOffer::where('product_id', $request->product_id ?? $indvOffer->product_id)
                    ->where('id', '!=', $id)
                    ->where(function ($query) use ($request, $indvOffer) {
                        $startDate = $request->start_date ?? $indvOffer->start_date;
                        $endDate = $request->end_date ?? $indvOffer->end_date;

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
                        'message' => 'Ya existe otra oferta activa para este producto en las fechas seleccionadas'
                    ], 409);
                }
            }

            $indvOffer->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Oferta individual actualizada correctamente',
                'data' => $indvOffer->load('product')
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Oferta individual no encontrada'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $indvOffer = IndividualOffer::findOrFail($id);
            $indvOffer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Oferta individual eliminada correctamente'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Oferta individual no encontrada'
            ], 404);
        }
    }
}
