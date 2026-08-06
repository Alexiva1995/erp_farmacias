<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReplenishmentConfig;
use App\Http\Requests\Suppliers\StoreAutoReplenishmentConfigRequest;
use App\Http\Requests\Suppliers\UpdateAutoReplenishmentConfigRequest;
use App\Http\Resources\AutoReplenishmentConfigResource;
use App\Services\AutoReplenishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutoReplenishmentConfigController extends Controller
{
    public function __construct(
        protected AutoReplenishmentService $service
    ) {}

    /**
     * Listar todas las configuraciones de reposición automática.
     */
    public function index(): AnonymousResourceCollection
    {
        $configs = $this->service->listConfigs();

        return AutoReplenishmentConfigResource::collection($configs);
    }

    /**
     * Crear una nueva configuración.
     */
    public function store(StoreAutoReplenishmentConfigRequest $request): JsonResponse
    {
        $config = $this->service->createConfig($request->validated());

        return (new AutoReplenishmentConfigResource($config))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Actualizar una configuración existente.
     */
    public function update(UpdateAutoReplenishmentConfigRequest $request, AutoReplenishmentConfig $config): AutoReplenishmentConfigResource
    {
        $updatedConfig = $this->service->updateConfig($config, $request->validated());

        return new AutoReplenishmentConfigResource($updatedConfig);
    }

    /**
     * Eliminar una configuración.
     */
    public function destroy(AutoReplenishmentConfig $config): JsonResponse
    {
        $this->service->deleteConfig($config);

        return response()->json(['message' => 'Configuración eliminada.']);
    }

    /**
     * Ejecutar manualmente una configuración específica.
     */
    public function run(AutoReplenishmentConfig $config): JsonResponse
    {
        $result = $this->service->runConfig($config);

        return response()->json([
            'message'           => $result['message'],
            'last_run_at'       => $result['last_run_at'],
            'last_run_products' => $result['last_run_products'],
            'last_run_orders'   => $result['last_run_orders'],
        ], $result['success'] ? 200 : 500);
    }
}
