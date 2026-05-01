<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\UpdateGeneralSettingRequest;
use App\Http\Resources\Configuration\GeneralSettingResource;
use App\Services\Configuration\GeneralSettingService;
use Illuminate\Http\JsonResponse;

class GeneralSettingController extends Controller
{
    /**
     * @var GeneralSettingService
     */
    protected $service;

    /**
     * Constructor del controlador.
     *
     * @param GeneralSettingService $service
     */
    public function __construct(GeneralSettingService $service)
    {
        $this->service = $service;
    }

    /**
     * Obtener la configuración general.
     *
     * @return GeneralSettingResource
     */
    public function index(): GeneralSettingResource
    {
        return new GeneralSettingResource($this->service->getSettings());
    }

    /**
     * Guardar o actualizar la configuración general.
     *
     * @param UpdateGeneralSettingRequest $request
     * @return JsonResponse
     */
    public function store(UpdateGeneralSettingRequest $request): JsonResponse
    {
        $setting = $this->service->updateSettings($request->validated());

        return response()->json([
            'message' => 'Configuración guardada correctamente',
            'data' => new GeneralSettingResource($setting)
        ]);
    }
}
