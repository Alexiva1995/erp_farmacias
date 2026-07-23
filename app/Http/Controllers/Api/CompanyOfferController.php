<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyOffer;
use App\Models\CompanyOfferScale;
use App\Http\Requests\Offers\StoreCompanyOfferRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyOfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CompanyOffer::with(['company', 'scales']);

            // Búsqueda por ID o nombre de empresa
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    // Buscar por ID de empresa (exacto)
                    if (is_numeric($search)) {
                        $q->where('company_id', $search);
                    }

                    // Buscar por nombre de empresa (like)
                    $q->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', "%{$search}%");
                    });
                });
            }

            // Filtro por estado activo/inactivo
            if ($request->has('is_active') && $request->is_active !== '') {
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_active', $isActive ? 1 : 0);
            }

            // Sorting
            if ($request->has('sort_by') && $request->has('order_by')) {
                $sortBy = $request->sort_by;
                $orderBy = $request->order_by;

                if (in_array($sortBy, ['id', 'start_date', 'end_date', 'is_active', 'created_at'])) {
                    $query->orderBy($sortBy, $orderBy);
                } elseif ($sortBy === 'company_id') {
                    $query->join('companies', 'company_offers.company_id', '=', 'companies.id')
                        ->orderBy('companies.name', $orderBy)
                        ->select('company_offers.*');
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Pagination
            $perPage = $request->get('items_per_page', 10);
            $page = $request->get('page', 1);
            $offers = $query->paginate($perPage, ['*'], 'page', $page);

            // Calcular las ventas acumuladas durante la vigencia de la oferta para la empresa
            $offers->getCollection()->transform(function ($offer) {
                $clientIds = \App\Models\Client::where('company_id', $offer->company_id)->pluck('id');

                $offer->sales_count = (int) \App\Models\Order::whereIn('client_id', $clientIds)
                    ->where('status', \App\Models\Order::COMPLETED)
                    ->where('order_date', '>=', $offer->start_date)
                    ->where('order_date', '<=', $offer->end_date . ' 23:59:59')
                    ->count();

                $offer->sales_amount = (float) \App\Models\Order::whereIn('client_id', $clientIds)
                    ->where('status', \App\Models\Order::COMPLETED)
                    ->where('order_date', '>=', $offer->start_date)
                    ->where('order_date', '<=', $offer->end_date . ' 23:59:59')
                    ->sum('total_amount_usd');

                return $offer;
            });

            return response()->json([
                'data' => $offers->items(),
                'total' => $offers->total(),
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'last_page' => $offers->lastPage(),
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al obtener las ofertas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreCompanyOfferRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();

            // Create the main offer
            $offer = CompanyOffer::create([
                'company_id' => $validated['company_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'],
            ]);

            // Create scales
            foreach ($validated['scales'] as $scaleData) {
                CompanyOfferScale::create([
                    'company_offer_id' => $offer->id,
                    'min_amount' => $scaleData['min_amount'],
                    'max_amount' => $scaleData['max_amount'],
                    'discount_percentage' => $scaleData['discount_percentage'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Oferta creada exitosamente.',
                'data' => $offer->load('scales')
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function update(StoreCompanyOfferRequest $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {

            // Buscar el modelo manualmente
            $companyOffer = CompanyOffer::find($id);

            if (!$companyOffer) {
                return response()->json([
                    'message' => 'Oferta no encontrada.',
                    'error' => 'Company offer with ID ' . $id . ' not found'
                ], 404);
            }

            $validated = $request->validated();

            // Actualizando la oferta principal
            $updated = $companyOffer->update([
                'company_id' => $validated['company_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'],
            ]);

            // Eliminar escalas existentes
            $deleted = CompanyOfferScale::where('company_offer_id', $companyOffer->id)->delete();

            // Crear nuevas escalas
            $scalesToCreate = [];
            foreach ($validated['scales'] as $index => $scaleData) {
                $scalesToCreate[] = [
                    'company_offer_id' => $companyOffer->id,
                    'min_amount' => $scaleData['min_amount'],
                    'max_amount' => $scaleData['max_amount'],
                    'discount_percentage' => $scaleData['discount_percentage'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

            }

            // Insertar todas las escalas
            if (!empty($scalesToCreate)) {
                $inserted = CompanyOfferScale::insert($scalesToCreate);
            }

            DB::commit();

            // Recargar con datos frescos
            $companyOffer->load(['company', 'scales']);

            return response()->json([
                'message' => 'Oferta actualizada exitosamente.',
                'data' => $companyOffer
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {

            // Buscar la oferta manualmente
            $companyOffer = CompanyOffer::find($id);

            if (!$companyOffer) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Oferta no encontrada.',
                    'error' => 'Company offer with ID ' . $id . ' not found'
                ], 404);
            }

            // 1. Eliminar escalas primero (de forma explícita)
            $scalesDeleted = CompanyOfferScale::where('company_offer_id', $id)->delete();

            // 2. Eliminar la oferta principal
            $offerDeleted = $companyOffer->delete();

            if (!$offerDeleted) {
                throw new \Exception('Failed to delete company offer');
            }

            DB::commit();

            // Verificar que realmente se eliminó
            $stillExists = CompanyOffer::find($id);

            return response()->json([
                'message' => 'Oferta eliminada exitosamente.'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recalculate($id): JsonResponse
    {
        try {
            $offer = CompanyOffer::with('scales')->findOrFail($id);

            // 1. Obtener IDs de clientes asociados a la empresa
            $clientIds = \App\Models\Client::where('company_id', $offer->company_id)->pluck('id');

            // 2. Sumar ventas completadas en el periodo de la oferta (en USD para consistencia)
            $totalSales = \App\Models\Order::whereIn('client_id', $clientIds)
                ->where('status', \App\Models\Order::COMPLETED)
                ->whereBetween('created_at', [$offer->start_date . ' 00:00:00', $offer->end_date . ' 23:59:59'])
                ->sum('total_amount_usd');

            // 3. Determinar el nuevo estado basándose en la escala mínima
            $minRequired = $offer->scales->min('min_amount') ?? 0;
            $newStatus = $totalSales >= $minRequired;

            // 4. Actualizar estado
            $offer->update(['is_active' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Recálculo completado exitosamente.',
                'total_sales' => (float) round($totalSales, 2),
                'min_required' => (float) $minRequired,
                'is_active' => (bool) $newStatus,
                'data' => $offer->load(['company', 'scales'])
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular la oferta: ' . $e->getMessage()
            ], 500);
        }
    }
}
