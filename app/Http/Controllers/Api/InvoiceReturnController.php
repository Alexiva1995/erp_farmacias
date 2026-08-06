<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\IndexInvoiceReturnsRequest;
use App\Http\Requests\Invoices\UpdateInvoiceReturnStatusRequest;
use App\Http\Resources\InvoiceReturnResource;
use App\Services\Invoices\InvoiceReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InvoiceReturnController extends Controller
{
    public function __construct(
        private InvoiceReturnService $invoiceReturnService
    ) {
    }

    /**
     * Listar devoluciones de facturas.
     */
    public function index(IndexInvoiceReturnsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['itemsPerPage'] ?? 10);

            $paginated = $this->invoiceReturnService->getReturns($filters, $perPage);

            return response()->json([
                'data' => InvoiceReturnResource::collection($paginated->items()),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceReturnController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener las devoluciones de facturas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar estado de una devolución específica.
     */
    public function updateStatus(UpdateInvoiceReturnStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $return = $this->invoiceReturnService->updateReturnStatus($id, $validated['status']);

            return response()->json([
                'message' => 'Estado de devolución actualizado exitosamente.',
                'data' => new InvoiceReturnResource($return),
            ]);
        } catch (\Exception $e) {
            Log::error("Error al actualizar estado de devolución #{$id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el estado de la devolución.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar estado masivo de devoluciones de una factura.
     */
    public function updateInvoiceStatus(UpdateInvoiceReturnStatusRequest $request, int $invoiceId): JsonResponse
    {
        try {
            $validated = $request->validated();
            $count = $this->invoiceReturnService->updateStatusByInvoice($invoiceId, $validated['status']);

            return response()->json([
                'message' => "Se actualizaron {$count} devoluciones asociadas a la factura.",
                'updated_count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error("Error al actualizar devoluciones de la factura #{$invoiceId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar las devoluciones de la factura.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
