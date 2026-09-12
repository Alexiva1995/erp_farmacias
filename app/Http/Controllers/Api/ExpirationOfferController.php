<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreExpirationOfferRequest;
use App\Http\Requests\Offers\UpdateExpirationOfferRequest;
use App\Http\Resources\ExpirationOfferResource;
use App\Models\ExpirationOffer;
use App\Services\ExpirationOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpirationOfferController extends Controller
{
    public function __construct(
        protected ExpirationOfferService $offerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['q', 'is_active', 'months', 'sortBy', 'orderBy', 'itemsPerPage']);
            $offers = $this->offerService->listOffers($filters);

            return response()->json([
                'success' => true,
                'data' => ExpirationOfferResource::collection($offers->items()),
                'total' => $offers->total(),
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'last_page' => $offers->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ofertas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreExpirationOfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerService->createOffer($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta creada exitosamente',
                'data' => new ExpirationOfferResource($offer)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    public function update(UpdateExpirationOfferRequest $request, ExpirationOffer $expirationOffer): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($expirationOffer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta actualizada exitosamente',
                'data' => new ExpirationOfferResource($updatedOffer)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    public function destroy(ExpirationOffer $expirationOffer): JsonResponse
    {
        try {
            $this->offerService->deleteOffer($expirationOffer);

            return response()->json([
                'success' => true,
                'message' => 'Oferta eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableProductLots(Request $request): JsonResponse
    {
        try {
            $months = (int) $request->get('months', 6);
            $availableLots = $this->offerService->getAvailableProductLots($months);

            return response()->json([
                'success' => true,
                'data' => $availableLots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lotes disponibles: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProducts(Request $request, ExpirationOffer $expirationOffer): JsonResponse
    {
        try {
            $filters = $request->only(['q', 'scope']);
            $products = $this->offerService->getQualifyingProducts($expirationOffer, $filters);

            return response()->json([
                'success' => true,
                'data' => $products,
                'total' => $products->count(),
                'offer' => new ExpirationOfferResource($expirationOffer)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los productos de la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleExclusion(Request $request, ExpirationOffer $expirationOffer): JsonResponse
    {
        try {
            $productId = (int) $request->input('product_id');
            if (!$productId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID del producto es requerido'
                ], 422);
            }

            $isExcluded = $this->offerService->toggleProductExclusion($expirationOffer, $productId);

            return response()->json([
                'success' => true,
                'is_excluded' => $isExcluded,
                'message' => $isExcluded
                    ? 'Producto excluido de la oferta en el Punto de Venta exitosamente'
                    : 'Producto reincorporado a la oferta en el Punto de Venta exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al modificar exclusión: ' . $e->getMessage()
            ], 500);
        }
    }
}
