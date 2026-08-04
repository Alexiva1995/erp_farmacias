<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreIndividualOfferRequest;
use App\Http\Requests\Offers\UpdateIndividualOfferRequest;
use App\Http\Resources\IndividualOfferResource;
use App\Models\IndividualOffer;
use App\Services\IndividualOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndividualOfferController extends Controller
{
    public function __construct(
        protected IndividualOfferService $offerService
    ) {}

    /**
     * Muestra la lista paginada y filtrada de ofertas individuales.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'search_id', 'sort_by', 'order_by', 'per_page']);
        $offers = $this->offerService->listOffers($filters);

        return IndividualOfferResource::collection($offers);
    }

    /**
     * Almacena una nueva oferta individual.
     */
    public function store(StoreIndividualOfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerService->createOffer($request->validated());

            return (new IndividualOfferResource($offer->load('product')))
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
     * Actualiza una oferta individual existente.
     */
    public function update(UpdateIndividualOfferRequest $request, IndividualOffer $individual): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($individual, $request->validated());

            return (new IndividualOfferResource($updatedOffer->load('product')))
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
     * Elimina una oferta individual.
     */
    public function destroy(IndividualOffer $individual): JsonResponse
    {
        $this->offerService->deleteOffer($individual);

        return response()->json([
            'status' => true,
            'message' => 'Oferta individual eliminada correctamente'
        ], 200);
    }
}
