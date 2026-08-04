<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreCategoryOfferRequest;
use App\Http\Requests\Offers\UpdateCategoryOfferRequest;
use App\Http\Resources\CategoryOfferResource;
use App\Models\CategoryOffer;
use App\Services\CategoryOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryOfferController extends Controller
{
    public function __construct(
        protected CategoryOfferService $offerService
    ) {}

    /**
     * Muestra la lista paginada y filtrada de ofertas por categoría.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'search_id', 'is_active', 'sort_by', 'order_by', 'per_page']);
        $offers = $this->offerService->listOffers($filters);

        return CategoryOfferResource::collection($offers);
    }

    /**
     * Muestra una oferta por categoría específica.
     */
    public function show(CategoryOffer $category): CategoryOfferResource
    {
        return new CategoryOfferResource($category->load('category'));
    }

    /**
     * Almacena una nueva oferta por categoría.
     */
    public function store(StoreCategoryOfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerService->createOffer($request->validated());

            return (new CategoryOfferResource($offer->load('category')))
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400);
        }
    }

    /**
     * Actualiza una oferta por categoría existente.
     */
    public function update(UpdateCategoryOfferRequest $request, CategoryOffer $category): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($category, $request->validated());

            return (new CategoryOfferResource($updatedOffer->load('category')))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400);
        }
    }

    /**
     * Elimina una oferta por categoría.
     */
    public function destroy(CategoryOffer $category): JsonResponse
    {
        $this->offerService->deleteOffer($category);

        return response()->json([
            'status' => true,
            'message' => 'Oferta por categoría eliminada correctamente'
        ], 200);
    }
}
