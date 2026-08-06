<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeneralPromotionRequest;
use App\Http\Requests\UpdateGeneralPromotionRequest;
use App\Http\Resources\GeneralPromotionResource;
use App\Models\GeneralPromotion;
use App\Services\GeneralPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeneralPromotionController extends Controller
{
    public function __construct(
        protected GeneralPromotionService $promotionService
    ) {}

    /**
     * Muestra la lista paginada y filtrada de promociones generales.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'search_id', 'sort_by', 'order_by', 'per_page']);
        $promotions = $this->promotionService->listPromotions($filters);

        return GeneralPromotionResource::collection($promotions);
    }

    /**
     * Almacena una nueva promoción general.
     */
    public function store(StoreGeneralPromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->createPromotion($request->validated());

        return (new GeneralPromotionResource($promotion))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Muestra una promoción específica.
     */
    public function show(GeneralPromotion $generalPromotion): GeneralPromotionResource
    {
        return new GeneralPromotionResource($generalPromotion);
    }

    /**
     * Actualiza una promoción específica.
     */
    public function update(UpdateGeneralPromotionRequest $request, GeneralPromotion $generalPromotion): GeneralPromotionResource
    {
        $updatedPromotion = $this->promotionService->updatePromotion($generalPromotion, $request->validated());

        return new GeneralPromotionResource($updatedPromotion);
    }

    /**
     * Elimina una promoción específica.
     */
    public function destroy(GeneralPromotion $generalPromotion): JsonResponse
    {
        $this->promotionService->deletePromotion($generalPromotion);

        return response()->json([
            'message' => 'Promoción eliminada con éxito.',
        ]);
    }
}
