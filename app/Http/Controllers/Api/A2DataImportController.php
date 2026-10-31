<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\ExecuteA2ImportRequest;
use App\Http\Requests\Configuration\RequestA2PreviewRequest;
use App\Http\Resources\Configuration\A2SyncTaskResource;
use App\Models\A2SyncTask;
use App\Services\Catalog\A2DataImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class A2DataImportController extends Controller
{
    public function __construct(
        protected A2DataImportService $service
    ) {}

    /**
     * Solicita una tarea de extracción y previsualización de a2.
     */
    public function requestPreview(RequestA2PreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $year = (int)($validated['year'] ?? 2026);
        $section = (string)$validated['section'];

        // Si tenemos la carpeta local en el servidor, procesamos de inmediato
        $localDataDir = base_path('A2/GEST - copia/GEST - copia/Empre001/Data');
        if (file_exists($localDataDir)) {
            // Usar extractor nativo directamente
            $extracted = [];
            $output = [];
            $code = 0;
            
            // Generar payload
            $script = "
import json, a2_extractor_service as a2
data_dir = r'{$localDataDir}'
result = {}
if '{$section}' in ['suppliers_payables', 'all']:
    result['suppliers'] = a2.extract_suppliers(data_dir)
    result['payables'] = a2.extract_payables(data_dir, {$year})
if '{$section}' in ['products_lots', 'all']:
    result['products'] = a2.extract_products(data_dir)
if '{$section}' in ['operations', 'sales', 'all']:
    result['operations'] = a2.extract_operations(data_dir, {$year})
if '{$section}' in ['clients', 'receivables', 'all']:
    result['clients'] = a2.extract_clients(data_dir)
    result['receivables'] = a2.extract_receivables(data_dir, {$year})
if '{$section}' in ['sales', 'all']:
    result['sales'] = a2.extract_sales(data_dir, {$year})
print(json.dumps(result))
";
            $tmpScript = base_path('temp_extract.py');
            file_put_contents($tmpScript, $script);
            exec("python " . escapeshellarg($tmpScript), $output, $code);
            @unlink($tmpScript);

            if ($code === 0 && !empty($output)) {
                $rawJson = implode('', $output);
                $extracted = json_decode($rawJson, true) ?: [];
                $preview = $this->service->buildPreview($extracted, $year);

                $task = A2SyncTask::create([
                    'section' => $section,
                    'year' => $year,
                    'status' => 'completed',
                    'extracted_data' => $extracted,
                    'preview_data' => $preview,
                    'created_by' => Auth::id(),
                ]);

                return response()->json([
                    'message' => 'Previsualización generada con éxito.',
                    'task' => new A2SyncTaskResource($task),
                ]);
            }
        }

        // Si no está local, se crea tarea en estado pending para que el poller de Python la atienda
        $task = A2SyncTask::create([
            'section' => $section,
            'year' => $year,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Solicitud de extracción enviada al puente a2.',
            'task' => new A2SyncTaskResource($task),
        ]);
    }

    /**
     * Endpoint consultado por el script poller en Python para buscar tareas pendientes.
     */
    public function pollPendingTask(): JsonResponse
    {
        $task = A2SyncTask::where('status', 'pending')->latest()->first();
        return response()->json([
            'task' => $task ? new A2SyncTaskResource($task) : null,
        ]);
    }

    /**
     * Endpoint donde el script poller entrega los datos crudos extraídos de a2.
     */
    public function submitTaskResult(Request $request, A2SyncTask $task): JsonResponse
    {
        $data = $request->input('data', []);
        $preview = $this->service->buildPreview($data, $task->year);

        $task->update([
            'status' => 'completed',
            'extracted_data' => $data,
            'preview_data' => $preview,
        ]);

        return response()->json([
            'message' => 'Datos procesados y previsualización generada.',
            'task' => new A2SyncTaskResource($task),
        ]);
    }

    /**
     * Consulta el estado actual de una tarea de sincronización.
     */
    public function checkTaskStatus(A2SyncTask $task): JsonResponse
    {
        return response()->json([
            'task' => new A2SyncTaskResource($task),
        ]);
    }

    /**
     * Ejecuta la importación definitiva de los datos validados y adaptados por el usuario.
     */
    public function executeImport(ExecuteA2ImportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stats = $this->service->executeImport($validated);

        if (!empty($validated['task_id'])) {
            A2SyncTask::where('id', $validated['task_id'])->update(['status' => 'imported']);
        }

        return response()->json([
            'message' => 'Datos importados exitosamente desde a2.',
            'stats' => $stats,
        ]);
    }
}
