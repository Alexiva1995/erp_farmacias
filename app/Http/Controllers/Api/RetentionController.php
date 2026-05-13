<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\RetentionResource;
use App\Models\Invoice;
use App\Services\Retention\RetentionService;
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
        $filters = $request->only(['start_date', 'end_date', 'supplier_id', 'search', 'is_generated', 'sortBy', 'orderBy']);
        $isGenerated = filter_var($request->input('is_generated', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $request->input('itemsPerPage', 10);

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
    }

    /**
     * Generar retenciones en lote.
     */
    public function bulkGenerate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:invoices,id'
        ]);

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
    public function batchGenerateAll(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        try {
            $count = $this->retentionService->generateAllPendingInRange(
                $request->start_date,
                $request->end_date
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
     * Actualizar número de retención.
     */
    public function update(Request $request, int $id)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'number' => 'required|string|max:50|unique:retentions,number,' . $id
        ], [
            'number.unique' => 'El número de comprobante ingresado ya se encuentra asignado a otra retención.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $retention = $this->retentionService->updateRetention($id, ['number' => $request->number]);
            return response()->json([
                'status' => 'success',
                'message' => 'Número de retención actualizado correctamente.',
                'data' => new RetentionResource($retention)
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
