<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceActionService;
use App\Services\Invoices\InvoiceQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Resources\InvoiceResource;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceDataRequest;
use App\Http\Requests\Invoices\SaveInvoiceDetailsRequest;
use App\Http\Requests\Invoice\MatchBarcodeRequest;
use App\Http\Requests\Invoice\UploadInvoicePhotoRequest;
use App\Http\Requests\Invoice\NextSequenceRequest;
use App\Http\Requests\Invoices\ApproveInvoiceRequest;
use App\Http\Requests\Invoices\BulkDeleteInvoicesRequest;
use App\Http\Requests\Invoices\IndexInvoiceRequest;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceActionService $invoiceActionService,
        private InvoiceQueryService $invoiceQueryService,
        private \App\Contracts\Suppliers\DronenaScraperServiceInterface $dronenaScraperService,
        private \App\Contracts\Suppliers\DrocercaScraperServiceInterface $drocercaScraperService,
        private \App\Contracts\Suppliers\MafartaScraperServiceInterface $mafartaScraperService,
        private \App\Contracts\Suppliers\CristmedicalsScraperServiceInterface $cristmedicalsScraperService,
        private \App\Contracts\Suppliers\DromegaScraperServiceInterface $dromegaScraperService,
        private \App\Contracts\Suppliers\DrosymcaScraperServiceInterface $drosymcaScraperService
    ) {
    }

    public function index(IndexInvoiceRequest $request)
    {
        try {
            $validated = $request->validated();
            if (!isset($validated['status'])) {
                $request->merge(['status' => ['pending']]);
            }
            $query = $this->invoiceQueryService->getInvoicesQuery($request);
            $totalUsdSum = (clone $query)->sum('total_usd');

            $perPage = (int) ($validated['itemsPerPage'] ?? 10);
            if ($perPage <= 0 || $perPage > 100) {
                $perPage = 10;
            }
            $paginatedResult = $query->paginate($perPage);

            return response()->json([
                'data' => InvoiceResource::collection($paginatedResult->items()),
                'total' => $paginatedResult->total(),
                'total_usd_sum' => (float) $totalUsdSum,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@index: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor al cargar facturas.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getDetails(Invoice $invoice)
    {
        $details = $this->invoiceQueryService->getInvoiceDetails($invoice);

        return response()->json(['data' => $details]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->invoiceActionService->createInvoice($request->validated());

        return response()->json([
            'message' => 'Factura registrada con éxito.',
            'invoice' => new InvoiceResource($invoice)
        ], 201);
    }

    public function destroy(Invoice $invoice)
    {
        try {
            $this->invoiceActionService->deleteInvoice($invoice);

            return response()->noContent();

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function bulkDelete(BulkDeleteInvoicesRequest $request)
    {
        try {
            $deletedCount = $this->invoiceActionService->bulkDeleteInvoicesBeforeDate(
                $request->validated()['before_date']
            );

            return response()->json([
                'message' => "Se cambiaron a estado eliminadas {$deletedCount} facturas correctamente.",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@bulkDelete: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al procesar la eliminación masiva de facturas.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function approve(ApproveInvoiceRequest $request, Invoice $invoice)
    {
        try {
            $approvedInvoice = $this->invoiceActionService->approveInvoice(
                $invoice,
                $request->validated()
            );

            return response()->json([
                'message' => 'Factura aprobada con éxito.',
                'invoice' => $approvedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar la factura: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, Invoice $invoice)
    {
        try {
            $rejectedInvoice = $this->invoiceActionService->rejectInvoice(
                $invoice
            );

            return response()->json([
                'message' => 'Factura rechazada con éxito.',
                'invoice' => $rejectedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar la factura: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Invoice $invoice)
    {
        $invoiceData = $this->invoiceQueryService->getInvoiceById($invoice);
        return new InvoiceResource($invoiceData);
    }

    public function getSuggestedDetails(Invoice $invoice)
    {
        $details = $this->invoiceQueryService->getSuggestedAndExistingDetails($invoice);

        return response()->json(['data' => $details]);
    }

    public function updateData(UpdateInvoiceDataRequest $request, Invoice $invoice)
    {
        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoiceData($invoice, $request->validated());

            return response()->json([
                'message' => 'Datos de la factura actualizados con éxito.',
                'invoice' => new InvoiceResource($updatedInvoice)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function saveDetails(SaveInvoiceDetailsRequest $request, Invoice $invoice)
    {
        try {
            $updatedInvoice = $this->invoiceActionService->saveInvoiceDetails($invoice, $request->validated());
            return response()->json([
                'message' => 'Progreso de la factura guardado con éxito.',
                'invoice' => new InvoiceResource($updatedInvoice)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function finalize(Request $request, Invoice $invoice)
    {
        try {
            $finalizedInvoice = $this->invoiceActionService->finalizeInvoice($invoice);

            return response()->json([
                'message' => 'Factura finalizada con éxito.',
                'invoice' => $finalizedInvoice
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateLocations(Request $request, Invoice $invoice)
    {
        try {
            $updatedInvoice = $this->invoiceActionService->updateInvoiceLocations($invoice, $request->all());

            return response()->json([
                'message' => 'Ubicaciones actualizadas con éxito.',
                'invoice' => $updatedInvoice
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getSupplierDebts(Request $request)
    {
        $supplierDebts = $this->invoiceQueryService->calculateSupplierDebts();

        return response()->json([
            'data' => [
                'total_debts' => $supplierDebts,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString(),
                'description' => 'Facturas pendientes de pago a proveedores'
            ],
            'message' => 'Deudas con proveedores calculadas con éxito.'
        ], 200);
    }

    public function returnInvoiceToPendingStatus(Invoice $invoice)
    {
        $response = $this->invoiceActionService->updateToPendingStatus($invoice);

        return response()->json([
            'status' => $response['status'],
            'message' => $response['message'] != null
                ? $response['message']
                : ($response['status']
                    ? 'Se devolvió la factura a pendientes'
                    : 'No se pudo devolver la factura a pendientes')
        ], 200);
    }

    public function matchBarcode(MatchBarcodeRequest $request)
    {
        try {
            $result = $this->invoiceQueryService->matchBarcodeWithAutoOrder(
                $request->barcode,
                $request->supplier_id,
                $request->auto_order_id ?? null
            );

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado en el sistema.'
                ], 404);
            }

            if (isset($result->status)) {
                return response()->json($result, 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al buscar el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadPhoto(UploadInvoicePhotoRequest $request, Invoice $invoice)
    {
        try {
            $updatedInvoice = $this->invoiceActionService->uploadInvoicePhoto($invoice, $request->file('file'));

            return response()->json([
                'message' => 'Foto de factura cargada con éxito.',
                'invoice' => $updatedInvoice,
                'photo_url' => asset('storage/' . $updatedInvoice->invoice_photo)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cargar la foto: ' . $e->getMessage()], 500);
        }
    }

    public function nextSequence(NextSequenceRequest $request)
    {
        $supplierId = $request->input('supplier_id');

        $lastInvoice = Invoice::where('supplier_id', $supplierId)
            ->where('invoice_number', 'like', 'INF-%')
            ->orderByRaw('CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastInvoice) {
            $numberStr = str_replace('INF-', '', $lastInvoice->invoice_number);
            $nextVal = ((int)$numberStr) + 1;
        } else {
            $nextVal = 1;
        }

        $formatted = 'INF-' . str_pad($nextVal, 6, '0', STR_PAD_LEFT);

        return response()->json([
            'next_sequence' => $formatted
        ]);
    }

    /**
     * Sincroniza las facturas de todos los proveedores automatizados (Dronena, Drocerca, Mafarta, Cristmedicals, Dromega).
     */
    public function syncAll(Request $request)
    {
        try {
            $results = [
                'dronena' => null,
                'drocerca' => null,
                'mafarta' => null,
                'cristmedicals' => null,
                'dromega' => null,
                'total_updated' => 0,
                'total_created' => 0,
                'total_skipped' => 0,
                'discrepancies' => [
                    'paid_in_erp_pending_in_dronena' => [],
                    'pending_in_erp_paid_in_dronena' => [],
                    'total_discrepancies' => 0,
                ],
                'messages' => [],
                'errors' => [],
            ];

            // 1. Dronena
            try {
                $dronena = $this->dronenaScraperService->syncInvoices();
                $results['dronena'] = $dronena;
                $results['total_updated'] += ($dronena['updated'] ?? 0);
                $results['total_skipped'] += ($dronena['skipped'] ?? 0);
                if (!empty($dronena['discrepancies'])) {
                    $results['discrepancies'] = $dronena['discrepancies'];
                }
                $results['messages'][] = "Dronena: {$dronena['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Dronena: ' . $e->getMessage());
                $results['errors'][] = 'Dronena: ' . $e->getMessage();
            }

            // 2. Drocerca
            try {
                $drocerca = $this->drocercaScraperService->syncInvoices();
                $results['drocerca'] = $drocerca;
                $results['total_updated'] += ($drocerca['updated'] ?? 0);
                $results['total_created'] += ($drocerca['created'] ?? 0);
                $results['total_skipped'] += ($drocerca['skipped'] ?? 0);
                $results['messages'][] = "Drocerca: {$drocerca['created']} nuevas, {$drocerca['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Drocerca: ' . $e->getMessage());
                $results['errors'][] = 'Drocerca: ' . $e->getMessage();
            }

            // 3. Mafarta
            try {
                $mafarta = $this->mafartaScraperService->syncInvoices();
                $results['mafarta'] = $mafarta;
                $results['total_updated'] += ($mafarta['updated'] ?? 0);
                $results['total_skipped'] += ($mafarta['skipped'] ?? 0);
                $results['messages'][] = "Mafarta: {$mafarta['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Mafarta: ' . $e->getMessage());
                $results['errors'][] = 'Mafarta: ' . $e->getMessage();
            }

            // 4. Cristmedicals
            try {
                $cristmedicals = $this->cristmedicalsScraperService->syncInvoices();
                $results['cristmedicals'] = $cristmedicals;
                $results['total_updated'] += ($cristmedicals['updated'] ?? 0);
                $results['total_created'] += ($cristmedicals['created'] ?? 0);
                $results['total_skipped'] += ($cristmedicals['skipped'] ?? 0);
                $results['messages'][] = "Cristmedicals: {$cristmedicals['created']} creadas, {$cristmedicals['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Cristmedicals: ' . $e->getMessage());
                $results['errors'][] = 'Cristmedicals: ' . $e->getMessage();
            }

            // 5. Droguería Mega (Dromega)
            try {
                $dromega = $this->dromegaScraperService->syncInvoices();
                $results['dromega'] = $dromega;
                $results['total_updated'] += ($dromega['updated'] ?? 0);
                $results['total_created'] += ($dromega['created'] ?? 0);
                $results['total_skipped'] += ($dromega['skipped'] ?? 0);
                $results['messages'][] = "Dromega: {$dromega['created']} creadas, {$dromega['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Dromega: ' . $e->getMessage());
                $results['errors'][] = 'Dromega: ' . $e->getMessage();
            }

            // 6. Drosymca
            try {
                $drosymca = $this->drosymcaScraperService->syncInvoices();
                $results['drosymca'] = $drosymca;
                $results['total_updated'] += ($drosymca['updated'] ?? 0);
                $results['total_created'] += ($drosymca['created'] ?? 0);
                $results['total_skipped'] += ($drosymca['skipped'] ?? 0);
                $results['messages'][] = "Drosymca: {$drosymca['created']} creadas, {$drosymca['updated']} actualizadas";
            } catch (\Throwable $e) {
                Log::error('Error syncAll Drosymca: ' . $e->getMessage());
                $results['errors'][] = 'Drosymca: ' . $e->getMessage();
            }

            $message = "Sincronización completada (" . implode(' | ', $results['messages']) . ")";
            if (!empty($results['errors'])) {
                $message .= " con advertencias en: " . implode(', ', $results['errors']);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error general en InvoiceController@syncAll: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar sincronización general: ' . $e->getMessage(),
                'details' => $results,
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas y fechas de vencimiento desde el portal Dronena.
     */
    public function syncDronena(Request $request)
    {
        try {
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->dronenaScraperService->syncInvoices($user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización completada. Facturas actualizadas: {$result['updated']}, Omitidas/No encontradas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncDronena: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Dronena: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas, vencimientos y totales fiscales desde el portal Drocerca.
     */
    public function syncDrocerca(Request $request)
    {
        try {
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->drocercaScraperService->syncInvoices($user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización con Drocerca completada. Creadas: {$result['created']}, Actualizadas: {$result['updated']}, Omitidas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncDrocerca: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Drocerca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas, vencimientos e indexación desde el portal SIC de Droguerías Cobeca / Mafarta.
     */
    public function syncMafarta(Request $request)
    {
        try {
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->mafartaScraperService->syncInvoices($user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización con Mafarta completada. Actualizadas: {$result['updated']}, Omitidas/No encontradas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncMafarta: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Mafarta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas, vencimientos, saldos con descuento y totales en Bs desde el portal web de Cristmedicals.
     */
    public function syncCristmedicals(Request $request)
    {
        try {
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->cristmedicalsScraperService->syncInvoices($user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización con Cristmedicals completada. Creadas: {$result['created']}, Actualizadas: {$result['updated']}, Omitidas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncCristmedicals: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Cristmedicals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas, vencimientos, saldos e indexación desde el portal web de Droguería Mega (Dromega).
     */
    public function syncDromega(Request $request)
    {
        try {
            $cookie = $request->input('cookie');
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->dromegaScraperService->syncInvoices($cookie, $user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización con Droguería Mega completada. Creadas: {$result['created']}, Actualizadas: {$result['updated']}, Omitidas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncDromega: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Droguería Mega: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincroniza las facturas, vencimientos, saldos e indexación desde el portal web de Drosymca.
     */
    public function syncDrosymca(Request $request)
    {
        try {
            $user = $request->input('username');
            $pass = $request->input('password');
            $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;

            $result = $this->drosymcaScraperService->syncInvoices($user, $pass, $supplierId);

            return response()->json([
                'success' => true,
                'message' => "Sincronización con Drosymca completada. Creadas: {$result['created']}, Actualizadas: {$result['updated']}, Omitidas: {$result['skipped']}.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en InvoiceController@syncDrosymca: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar facturas desde Drosymca: ' . $e->getMessage()
            ], 500);
        }
    }
}
