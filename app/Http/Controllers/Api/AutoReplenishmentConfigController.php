<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReplenishmentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AutoReplenishmentConfigController extends Controller
{
    /**
     * Listar todas las configuraciones de reposición automática.
     */
    public function index(): JsonResponse
    {
        $configs = AutoReplenishmentConfig::with('supplier:id,name')
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($configs);
    }

    /**
     * Crear una nueva configuración.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'is_active'           => 'boolean',
            'tipo_filtracion'     => 'required|in:average,sales,combinado',
            'lapso_de_tiempo'     => 'required|string|in:7 days,15 days,1 month,3 month,6 month,1 year',
            'min_solicitar'       => 'numeric|min:0',
            'con_descuento'       => 'boolean',
            'stock_filter'        => 'string|in:fallas,all',
            'supplier_id'         => 'nullable|integer|exists:suppliers,id',
            'group_ids'           => 'nullable|array',
            'group_ids.*'         => 'integer',
            'schedule_expression' => 'required|string|max:50',
        ]);

        $config = AutoReplenishmentConfig::create($data);

        return response()->json($config->load('supplier:id,name'), 201);
    }

    /**
     * Actualizar una configuración existente.
     */
    public function update(Request $request, AutoReplenishmentConfig $config): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:100',
            'is_active'           => 'sometimes|boolean',
            'tipo_filtracion'     => 'sometimes|in:average,sales,combinado',
            'lapso_de_tiempo'     => 'sometimes|string|in:7 days,15 days,1 month,3 month,6 month,1 year',
            'min_solicitar'       => 'sometimes|numeric|min:0',
            'con_descuento'       => 'sometimes|boolean',
            'stock_filter'        => 'sometimes|string|in:fallas,all',
            'supplier_id'         => 'nullable|integer|exists:suppliers,id',
            'group_ids'           => 'nullable|array',
            'group_ids.*'         => 'integer',
            'schedule_expression' => 'sometimes|string|max:50',
        ]);

        $config->update($data);

        return response()->json($config->fresh()->load('supplier:id,name'));
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
