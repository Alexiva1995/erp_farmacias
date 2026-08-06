<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreDoctorOfferRequest;
use App\Http\Requests\Offers\UpdateDoctorOfferRequest;
use App\Http\Resources\DoctorOfferResource;
use App\Models\DoctorOffer;
use App\Services\DoctorOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorOfferController extends Controller
{
    public function __construct(
        protected DoctorOfferService $offerService
    ) {}

    /**
     * Obtener lista paginada de las ofertas médicas.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'sort_by', 'sort_order', 'order_by', 'per_page']);
            $offers = $this->offerService->listOffers($filters);

            return response()->json([
                'success' => true,
                'data' => DoctorOfferResource::collection($offers->items()),
                'total' => $offers->total(),
                'current_page' => $offers->currentPage(),
                'per_page' => $offers->perPage(),
                'last_page' => $offers->lastPage()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las ofertas médicas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Creación de la oferta médica.
     */
    public function store(StoreDoctorOfferRequest $request): JsonResponse
    {
        try {
            $doctorOffer = $this->offerService->createOffer($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta creada exitosamente',
                'data' => new DoctorOfferResource($doctorOffer)
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualización de una oferta médica.
     */
    public function update(UpdateDoctorOfferRequest $request, DoctorOffer $doctorOffer): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($doctorOffer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta actualizada exitosamente.',
                'data' => new DoctorOfferResource($updatedOffer)
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una oferta médica.
     */
    public function destroy(DoctorOffer $doctorOffer): JsonResponse
    {
        try {
            $this->offerService->deleteOffer($doctorOffer);

            return response()->json([
                'message' => 'Oferta eliminada exitosamente.',
                'success' => true
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
