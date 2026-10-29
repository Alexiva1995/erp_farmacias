<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\VerticalPresetServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\ApplyVerticalPresetRequest;
use App\Http\Requests\Configuration\UpdateGeneralSettingRequest;
use App\Http\Resources\Configuration\GeneralSettingResource;
use App\Services\Configuration\GeneralSettingService;
use Illuminate\Http\JsonResponse;

class GeneralSettingController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(
        protected GeneralSettingService $service,
        protected VerticalPresetServiceInterface $presetService
    ) {}

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
            'data' => new GeneralSettingResource($setting),
        ]);
    }

    /**
     * Obtener el catálogo de presets de verticales disponibles.
     *
     * @return JsonResponse
     */
    public function presets(): JsonResponse
    {
        return response()->json([
            'data' => $this->presetService->getAvailablePresets(),
        ]);
    }

    /**
     * Aplicar un preset de vertical con 1 solo clic preservando identidad corporativa.
     *
     * @param ApplyVerticalPresetRequest $request
     * @return JsonResponse
     */
    public function applyPreset(ApplyVerticalPresetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $preserveIdentity = $validated['preserve_identity'] ?? true;

        $setting = $this->presetService->applyPreset($validated['vertical'], (bool) $preserveIdentity);

        return response()->json([
            'message' => 'Plantilla de vertical aplicada exitosamente',
            'data' => new GeneralSettingResource($setting),
        ]);
    }

    /**
     * Registrar una nueva vertical personalizada dinámica.
     */
    public function storeCustomPreset(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|regex:/^[a-z0-9_]+$/|min:2|max:50',
            'label' => 'required|string|min:2|max:150',
            'description' => 'required|string|min:5|max:300',
            'config' => 'required|array',
        ]);

        $custom = $this->presetService->registerCustomPreset(
            $validated['key'],
            $validated['label'],
            $validated['description'],
            $validated['config']
        );

        return response()->json([
            'success' => true,
            'message' => 'Nueva vertical personalizada creada exitosamente.',
            'data' => $custom,
        ], 201);
    }
}
