<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\RetentionResource;
use App\Models\Invoice;
use App\Services\Retention\RetentionService;
use App\Http\Requests\Retention\BulkGenerateRequest;
use App\Http\Requests\Retention\BatchGenerateAllRequest;
use App\Http\Requests\Retention\UpdateRetentionRequest;
use App\Http\Requests\Retention\UpdateInvoiceControlNumberRequest;
use App\Http\Requests\Retention\OmitUntilDateRequest;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RetentionController extends Controller
{
    public function __construct(
        protected RetentionService $retentionService
    ) {}

    /**
     * Listado de facturas con IVA para retenciones.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'supplier_id', 'search', 'is_generated', 'sortBy', 'orderBy']);
            $isGenerated = filter_var($request->input('is_generated', false), FILTER_VALIDATE_BOOLEAN);
            $perPage = (int) $request->input('itemsPerPage', 10);
            if ($perPage <= 0) {
                $perPage = 10;
            }

            if ($isGenerated) {
                $data = $this->retentionService->getGeneratedRetentions($filters, $perPage);
                $items = RetentionResource::collection($data);
            } else {
                $data = $this->retentionService->getInvoicesWithTax($filters, $perPage);
                $items = InvoiceResource::collection($data);
            }

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'total' => $data->total(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en RetentionController@index: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar los datos de retenciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar retenciones en lote.
     */
    public function bulkGenerate(BulkGenerateRequest $request)
    {
        try {
            $retention = $this->retentionService->generateRetentions($request->ids);

            return response()->json([
                'status' => 'success',
                'message' => "Se generó el comprobante {$retention->number} correctamente.",
                'retention_id' => $retention->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Generar todas las retenciones pendientes por rango de fecha.
     */
    public function batchGenerateAll(BatchGenerateAllRequest $request)
    {
        try {
            $count = $this->retentionService->generateAllPendingInRange(
                $request->start_date,
                $request->end_date,
                $request->input('retention_date')
            );

            return response()->json([
                'status' => 'success',
                'message' => "Se generaron $count retenciones correctamente.",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Generar y descargar PDF de retención.
     */
    public function downloadPdf(Request $request)
    {
        $retentionId = $request->input('retention_id');
        $invoiceIds = $request->input('ids'); // Fallback para generación inmediata
        
        try {
            if ($retentionId) {
                $source = \App\Models\Retention::findOrFail($retentionId);
            } elseif ($invoiceIds) {
                $source = is_array($invoiceIds) ? $invoiceIds : explode(',', $invoiceIds);
            } else {
                return response()->json(['status' => 'error', 'message' => 'No se especificó origen'], 400);
            }

            $data = $this->retentionService->prepareRetentionData($source);
            $pdf = Pdf::loadView('pdf.retention', $data);
            
            $filename = "retencion_" . str_replace(['/', ' '], '_', $data['comprobante']['number']) . ".pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Eliminar una retención (desvincula facturas y elimina el registro).
     */
    public function destroy(int $id)
    {
        try {
            $this->retentionService->deleteRetention($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Retención eliminada correctamente y facturas desvinculadas.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Obtener una retención con sus facturas asociadas.
     */
    public function show(int $id)
    {
        try {
            $retention = $this->retentionService->getRetentionWithInvoices($id);
            return response()->json([
                'status' => 'success',
                'data' => new RetentionResource($retention),
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Actualizar número, fecha de retención y números de control de facturas vinculadas.
     */
    public function update(UpdateInvoiceControlNumberRequest $request, int $id)
    {
        try {
            $data = [];
            if ($request->filled('number')) {
                $data['number'] = $request->number;
            }
            if ($request->filled('date')) {
                $data['date'] = $request->date;
            }

            $retention = $this->retentionService->updateRetentionWithInvoices(
                $id,
                $data,
                $request->input('invoices', [])
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Retención actualizada correctamente.',
                'data'    => new RetentionResource($retention),
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Omite todas las facturas pendientes con fecha igual o anterior a una fecha límite.
     */
    public function omitUntilDate(OmitUntilDateRequest $request)
    {
        try {
            $count = $this->retentionService->omitInvoicesUntilDate($request->cutoff_date);

            return response()->json([
                'status' => 'success',
                'message' => "Se omitieron {$count} facturas pendientes anteriores o iguales a {$request->cutoff_date}.",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Restaura todas las facturas omitidas devolviéndolas a estado pendiente.
     */
    public function restoreOmitted()
    {
        try {
            $count = $this->retentionService->restoreOmittedInvoices();

            return response()->json([
                'status' => 'success',
                'message' => "Se restauraron {$count} facturas omitidas.",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
