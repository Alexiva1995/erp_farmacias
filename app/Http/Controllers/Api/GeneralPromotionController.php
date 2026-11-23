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

    /**
     * Devuelve métricas unificadas de uso, ventas y margen bruto de promociones.
     */
    public function metrics(\App\Services\Promotions\PromotionResolverService $resolver): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resolver->getPromotionMetrics(),
        ]);
    }

    /**
     * Aplica o remueve un código de cupón en una orden y recalcula promociones generales.
     */
    public function applyCoupon(
        Request $request,
        \App\Models\Order $order,
        \App\Services\Order\OrderActionService $orderActionService
    ): JsonResponse {
        $coupon = strtoupper(trim((string) $request->input('coupon_code', '')));

        if ($coupon !== '') {
            $exists = GeneralPromotion::where('is_active', true)
                ->whereRaw('UPPER(coupon_code) = ?', [$coupon])
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'El código de cupón no existe o no está activo.',
                ], 422);
            }
        }

        $order->applied_coupon_code = $coupon !== '' ? $coupon : null;
        $order->save();

        $orderActionService->applyGeneralPromotions($order);
        $order->updateTotals();

        return response()->json([
            'success' => true,
            'message' => $coupon !== '' ? "Cupón {$coupon} aplicado." : 'Cupón removido.',
            'data' => [
                'applied_coupon_code' => $order->applied_coupon_code,
                'total_amount' => $order->total_amount,
                'total_amount_usd' => $order->total_amount_usd,
            ],
        ]);
    }
}
