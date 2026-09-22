<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalContribution\BatchImportFiscalContributionsRequest;
use App\Http\Requests\FiscalContribution\ParseRawSeniatRequest;
use App\Http\Requests\FiscalContribution\StoreFiscalContributionRequest;
use App\Http\Requests\FiscalContribution\TogglePaymentStatusRequest;
use App\Http\Requests\FiscalContribution\UpdateFiscalContributionRequest;
use App\Http\Resources\FiscalContributionResource;
use App\Services\FiscalContribution\FiscalContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FiscalContributionController extends Controller
{
    public function __construct(
        protected FiscalContributionService $service
    ) {}

    /**
     * Listado paginado de contribuciones fiscales con KPIs y filtros.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'tax_type', 'period', 'status', 'start_date', 'end_date', 'sortBy', 'orderBy']);
            $perPage = (int)$request->input('itemsPerPage', 10);
            if ($perPage <= 0) {
                $perPage = 10;
            }

            $paginated = $this->service->getPaginated($filters, $perPage);
            $kpis = $this->service->getKpis($filters);

            return response()->json([
                'status'     => 'success',
                'data'       => FiscalContributionResource::collection($paginated),
                'kpis'       => $kpis,
                'pagination' => [
                    'total'        => $paginated->total(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@index: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al cargar las contribuciones fiscales.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar una nueva contribución fiscal manual.
     */
    public function store(StoreFiscalContributionRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $contribution = $this->service->create($request->validated(), $userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Contribución fiscal registrada exitosamente.',
                'data'    => new FiscalContributionResource($contribution),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@store: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar la contribución fiscal.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Actualizar una contribución fiscal existente.
     */
    public function update(UpdateFiscalContributionRequest $request, int $id): JsonResponse
    {
        try {
            $contribution = $this->service->update($id, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Contribución fiscal actualizada correctamente.',
                'data'    => new FiscalContributionResource($contribution),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@update: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar la contribución fiscal.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Eliminar una contribución fiscal.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Contribución fiscal eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@destroy: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al eliminar la contribución fiscal.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Importación en lote (Smart Paste o Bot).
     */
    public function batchImport(BatchImportFiscalContributionsRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $items = $request->validated('items');
            $source = $request->validated('source') ?? 'smart_paste';

            $result = $this->service->batchImport($items, $source, $userId);

            return response()->json([
                'status'  => 'success',
                'message' => "Se procesaron {$result['total']} compromisos fiscales ({$result['inserted']} nuevos, {$result['updated']} actualizados).",
                'result'  => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@batchImport: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al importar los compromisos fiscales.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Parsear texto crudo copiado directamente del portal SENIAT en tiempo real.
     */
    public function parseRawSeniat(ParseRawSeniatRequest $request): JsonResponse
    {
        try {
            $rawText = $request->validated('raw_text');
            $parsed = $this->service->parseSeniatRawText($rawText);

            return response()->json([
                'status' => 'success',
                'data'   => $parsed,
                'count'  => count($parsed),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@parseRawSeniat: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo interpretar el texto copiado del SENIAT.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Alternar o marcar estado de pago de una contribución fiscal.
     */
    public function togglePayment(TogglePaymentStatusRequest $request, int $id): JsonResponse
    {
        try {
            $contribution = $this->service->togglePaymentStatus($id, $request->validated());

            $msg = $contribution->status === 'paid'
                ? 'Compromiso marcado como PAGADO exitosamente.'
                : 'Compromiso devuelto a estado PENDIENTE.';

            return response()->json([
                'status'  => 'success',
                'message' => $msg,
                'data'    => new FiscalContributionResource($contribution),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en FiscalContributionController@togglePayment: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al cambiar el estado de pago.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }
}
