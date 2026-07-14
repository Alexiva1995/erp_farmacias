<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductFailureRequest;
use App\Http\Resources\ProductFailureResource;
use App\Contracts\ProductFailure as ProductFailureContract;
use Illuminate\Support\Facades\Auth;

class ProductFailureController extends Controller
{
    public function __construct(
        protected ProductFailureContract $productFailureService
    ) {}

    /**
     * Almacenar un nuevo reporte de falla.
     */
    public function store(StoreProductFailureRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        $failure = $this->productFailureService->store($validated);

        return (new ProductFailureResource($failure))
            ->additional([
                'status' => 'success',
                'message' => 'Reporte de falla guardado correctamente.'
            ])
            ->response()
            ->setStatusCode(201);
    }
}
