<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReplenishmentConfig;
use App\Http\Requests\Suppliers\StoreAutoReplenishmentConfigRequest;
use App\Http\Requests\Suppliers\UpdateAutoReplenishmentConfigRequest;
use App\Http\Resources\AutoReplenishmentConfigResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;

class AutoReplenishmentConfigController extends Controller
{
    /**
     * Listar todas las configuraciones de reposición automática.
     */
    public function index(): AnonymousResourceCollection
    {
        $configs = AutoReplenishmentConfig::with('supplier:id,name')
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return AutoReplenishmentConfigResource::collection($configs);
    }

    /**
     * Crear una nueva configuración.
     */
    public function store(StoreAutoReplenishmentConfigRequest $request): JsonResponse
    {
        $config = AutoReplenishmentConfig::create($request->validated());

        return (new AutoReplenishmentConfigResource($config->load('supplier:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Actualizar una configuración existente.
     */
    public function update(UpdateAutoReplenishmentConfigRequest $request, AutoReplenishmentConfig $config): AutoReplenishmentConfigResource
    {
        $config->update($request->validated());

        return new AutoReplenishmentConfigResource($config->fresh()->load('supplier:id,name'));
    }

    /**
     * Eliminar una configuración.
     */
    public function destroy(AutoReplenishmentConfig $config): JsonResponse
    {
        $config->delete();
        return response()->json(['message' => 'Configuración eliminada.']);
    }

    /**
     * Ejecutar manualmente una configuración específica.
     */
    public function run(AutoReplenishmentConfig $config): JsonResponse
    {
        // Ejecutar el comando de forma síncrona para obtener el resultado inmediato
        $exitCode = Artisan::call('replenishment:run', [
            '--config' => $config->id,
        ]);

        $config->refresh();

        return response()->json([
            'message'           => $exitCode === 0 ? 'Ejecución completada.' : 'La ejecución terminó con errores. Revisa los logs.',
            'last_run_at'       => $config->last_run_at,
            'last_run_products' => $config->last_run_products,
            'last_run_orders'   => $config->last_run_orders,
        ], $exitCode === 0 ? 200 : 500);
    }
}
