<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreCompanyOfferRequest;
use App\Http\Resources\CompanyOfferResource;
use App\Models\CompanyOffer;
use App\Services\CompanyOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyOfferController extends Controller
{
    public function __construct(
        protected CompanyOfferService $offerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'is_active', 'sort_by', 'order_by', 'items_per_page', 'page']);
            $offers = $this->offerService->listOffers($filters);

            return response()->json([
                'data' => CompanyOfferResource::collection($offers->items()),
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
        try {
            $offer = $this->offerService->createOffer($request->validated());

            return response()->json([
                'message' => 'Oferta creada exitosamente.',
                'data' => new CompanyOfferResource($offer)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al crear la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(StoreCompanyOfferRequest $request, CompanyOffer $companyOffer): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($companyOffer, $request->validated());

            return response()->json([
                'message' => 'Oferta actualizada exitosamente.',
                'data' => new CompanyOfferResource($updatedOffer)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(CompanyOffer $companyOffer): JsonResponse
    {
        try {
            $this->offerService->deleteOffer($companyOffer);

            return response()->json([
                'message' => 'Oferta eliminada exitosamente.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recalculate(CompanyOffer $companyOffer): JsonResponse
    {
        try {
            $result = $this->offerService->recalculateOffer($companyOffer);

            return response()->json([
                'success' => true,
                'message' => 'Recálculo completado exitosamente.',
                'total_sales' => $result['total_sales'],
                'min_required' => $result['min_required'],
                'is_active' => $result['is_active'],
                'data' => new CompanyOfferResource($result['offer'])
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular la oferta: ' . $e->getMessage()
            ], 500);
        }
    }
}
