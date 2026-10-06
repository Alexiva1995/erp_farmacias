<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\UpdateEmailSyncConfigRequest;
use App\Http\Resources\Configuration\EmailSyncConfigResource;
use App\Services\Configuration\EmailSyncConfigService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailSyncConfigController extends Controller
{
    public function __construct(
        protected EmailSyncConfigService $service
    ) {
    }

    /**
     * Obtiene la configuración actual de sincronización Gmail.
     */
    public function show(): EmailSyncConfigResource
    {
        return new EmailSyncConfigResource($this->service->getConfig());
    }

    /**
     * Guarda o actualiza la configuración de sincronización Gmail.
     */
    public function update(UpdateEmailSyncConfigRequest $request): JsonResponse
    {
        $setting = $this->service->updateConfig($request->validated());

        return response()->json([
            'message' => 'Configuración de sincronización de Gmail guardada con éxito.',
            'data' => new EmailSyncConfigResource($setting),
        ]);
    }

    /**
     * Prueba la conexión en vivo con Gmail IMAP.
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $result = $this->service->testConnection($request->all());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ejecuta una sincronización inmediata de los correos de proveedores.
     */
    public function runSync(): JsonResponse
    {
        try {
            $result = $this->service->syncCatalogs();

            return response()->json([
                'success' => true,
                'message' => 'Sincronización de catálogos por correo ejecutada correctamente.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar catálogos por correo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
