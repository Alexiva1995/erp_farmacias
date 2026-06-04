<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\GeneralPromotion;
use App\Http\Requests\StoreGeneralPromotionRequest;
use App\Http\Requests\UpdateGeneralPromotionRequest;
use App\Http\Resources\GeneralPromotionResource;

class GeneralPromotionController extends Controller
{
    /**
     * Muestra la lista de promociones generales.
     */
    public function index()
    {
        $promotions = GeneralPromotion::all();
        return GeneralPromotionResource::collection($promotions);
    }

    /**
     * Almacena una nueva promoción general.
     */
    public function store(StoreGeneralPromotionRequest $request)
    {
        $promotion = GeneralPromotion::create($request->validated());
        return new GeneralPromotionResource($promotion);
    }

    /**
     * Muestra una promoción específica.
     */
    public function show(GeneralPromotion $generalPromotion)
    {
        return new GeneralPromotionResource($generalPromotion);
    }

    /**
     * Actualiza una promoción específica.
     */
    public function update(UpdateGeneralPromotionRequest $request, GeneralPromotion $generalPromotion)
    {
        $generalPromotion->update($request->validated());
        return new GeneralPromotionResource($generalPromotion);
    }

    /**
     * Elimina una promoción específica.
     */
    public function destroy(GeneralPromotion $generalPromotion)
    {
        $generalPromotion->delete();
        return response()->json([
            'message' => 'Promoción eliminada con éxito.'
        ]);
    }
}
