<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\AddProductToPrescriptionOfferRequest;
use App\Http\Requests\Offers\RemoveProductFromOfferRequest;
use App\Http\Requests\Offers\StorePrescriptionOfferRequest;
use App\Http\Requests\Offers\UpdatePrescriptionOfferRequest;
use App\Http\Requests\Offers\UpdateProductQuantityRequest;
use App\Http\Resources\PrescriptionOfferResource;
use App\Models\PrescriptionOffer;
use App\Services\PrescriptionOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionOfferController extends Controller
{
    public function __construct(
        protected PrescriptionOfferService $offerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['id', 'is_active', 'search', 'sort_by', 'order', 'per_page']);
            $offers = $this->offerService->listOffers($filters);

            return response()->json([
                'success' => true,
                'data' => PrescriptionOfferResource::collection($offers->items()),
                'total' => $offers->total(),
                'per_page' => $offers->perPage(),
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ofertas de recetas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StorePrescriptionOfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerService->createOffer($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta creada exitosamente',
                'data' => new PrescriptionOfferResource($offer)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PrescriptionOfferResource($prescriptionOffer)
        ]);
    }

    public function update(UpdatePrescriptionOfferRequest $request, PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        try {
            $updatedOffer = $this->offerService->updateOffer($prescriptionOffer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta actualizada exitosamente',
                'data' => new PrescriptionOfferResource($updatedOffer)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        try {
            $this->offerService->deleteOffer($prescriptionOffer);

            return response()->json([
                'success' => true,
                'message' => 'Oferta de receta eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la oferta de receta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addProductToOffer(AddProductToPrescriptionOfferRequest $request, PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        try {
            $data = $this->offerService->addProductToOffer($prescriptionOffer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado a la oferta exitosamente',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar producto a la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateProductQuantity(UpdateProductQuantityRequest $request, PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        try {
            $data = $this->offerService->updateProductQuantity($prescriptionOffer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cantidad del producto actualizada exitosamente',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    public function removeProductFromOffer(RemoveProductFromOfferRequest $request, PrescriptionOffer $prescriptionOffer): JsonResponse
    {
        try {
            $data = $this->offerService->removeProductFromOffer($prescriptionOffer, (int) $request->validated()['product_id']);

            return response()->json([
                'success' => true,
                'message' => 'Producto removido de la oferta exitosamente',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }
}
