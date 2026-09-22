<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\StoreFiscalCommandRequest;
use App\Http\Requests\Fiscal\ConfirmFiscalPrintRequest;
use App\Http\Resources\Fiscal\FiscalCommandResource;
use App\Models\FiscalHistory;
use App\Services\Fiscal\FiscalActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FiscalPrinterController extends Controller
{
    public function __construct(
        protected FiscalActionService $service
    ) {}

    /**
     * Get the next pending fiscal invoice to be printed.
     */
    public function getPending()
    {
        try {
            \Illuminate\Support\Facades\Cache::put('fiscal_bridge_last_seen', now(), 120);

            $pending = FiscalHistory::where('is_queued', true)
                ->whereNull('invoice_number')
                ->with(['details', 'user.employee', 'order.client'])
                ->orderBy('created_at', 'asc')
                ->first();

            return response()->json($pending);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@getPending: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener facturas pendientes'], 500);
        }
    }

    /**
     * Confirm that a fiscal invoice has been printed.
     */
    public function confirm(ConfirmFiscalPrintRequest $request, $id)
    {
        try {
            \Illuminate\Support\Facades\Cache::put('fiscal_bridge_last_seen', now(), 120);
            
            $fiscal = FiscalHistory::where('id', $id)->orWhere('order_id', $id)->first();
            if (!$fiscal) {
                return response()->json(['error' => "Registro fiscal no encontrado para ID {$id}"], 404);
            }

            $targetInvoiceNumber = $request->invoice_number;
            $updateData = [
                'invoice_number' => $targetInvoiceNumber,
                'is_queued' => false,
                'invoice_date' => now(),
            ];

            if (!empty($request->fiscal_id)) {
                $updateData['fiscal_id'] = $request->fiscal_id;
            }

            $fiscal->update($updateData);

            return response()->json([
                'message' => 'Factura confirmada exitosamente',
                'invoice_number' => $targetInvoiceNumber,
                'fiscal_id' => $fiscal->fiscal_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@confirm: ' . $e->getMessage());
            return response()->json(['error' => 'Error al confirmar la impresión'], 500);
        }
    }

    /**
     * Confirm that a fiscal invoice has been printed (REPLICA).
     * Ensures fiscal_id is always saved.
     */
    public function confirmReplica(ConfirmFiscalPrintRequest $request, $id)
    {
        try {
            \Illuminate\Support\Facades\Cache::put('fiscal_bridge_last_seen', now(), 120);
            
            $fiscal = FiscalHistory::where('id', $id)->orWhere('order_id', $id)->first();
            if (!$fiscal) {
                return response()->json(['error' => "Registro fiscal no encontrado para ID {$id}"], 404);
            }
            
            $targetInvoiceNumber = $request->invoice_number;
            $updateData = [
                'invoice_number' => $targetInvoiceNumber,
                'is_queued' => false,
                'invoice_date' => now(),
            ];

            if (!empty($request->fiscal_id)) {
                $updateData['fiscal_id'] = $request->fiscal_id;
            }

            $fiscal->update($updateData);

            return response()->json([
                'message' => 'Factura confirmada exitosamente en RÉPLICA',
                'invoice_number' => $targetInvoiceNumber,
                'fiscal_id' => $fiscal->fiscal_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@confirmReplica: ' . $e->getMessage());
            return response()->json(['error' => 'Error al confirmar la impresión en REPLICA'], 500);
        }
    }

    /**
     * Queue an order for fiscal printing.
     */
    public function queue(Request $request, $orderId)
    {
        try {
            $fiscal = FiscalHistory::where('order_id', $orderId)->first();
            
            if (!$fiscal) {
                return response()->json(['error' => 'No se encontró registro fiscal para esta orden'], 404);
            }

            $fiscal->update(['is_queued' => true]);

            return response()->json(['message' => 'Orden encolada para impresión fiscal']);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@queue: ' . $e->getMessage());
            return response()->json(['error' => 'Error al encolar la orden'], 500);
        }
    }

    /**
     * Enqueue a generic fiscal command.
     */
    public function storeCommand(StoreFiscalCommandRequest $request)
    {
        try {
            $cmd = $this->service->enqueueCommand($request->command, $request->payload);
            return new FiscalCommandResource($cmd);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@storeCommand: ' . $e->getMessage());
            return response()->json(['error' => 'Error al encolar el comando'], 500);
        }
    }

    /**
     * Get the next pending general fiscal command (for Python).
     */
    public function getPendingCommand()
    {
        try {
            \Illuminate\Support\Facades\Cache::put('fiscal_bridge_last_seen', now(), 120);
            $pending = $this->service->getNextCommand();
            return $pending ? new FiscalCommandResource($pending) : response()->json(null);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@getPendingCommand: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener comandos pendientes'], 500);
        }
    }

    /**
     * Confirm execution of a general fiscal command (for Python).
     */
    public function confirmCommand(Request $request, $id)
    {
        try {
            $this->service->confirmCommand($id, $request->all());
            return response()->json(['message' => 'Comando confirmado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@confirmCommand: ' . $e->getMessage());
            return response()->json(['error' => 'Error al confirmar el comando'], 500);
        }
    }

    /**
     * Get recent history of general commands.
     */
    public function history()
    {
        try {
            $history = $this->service->getHistory(15);
            return FiscalCommandResource::collection($history);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@history: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener historial'], 500);
        }
    }

    /**
     * Look up fiscal invoice by number to autofill credit note data.
     */
    public function lookupInvoice(Request $request)
    {
        try {
            $query = trim((string) $request->input('invoice_number', ''));
            
            // Serial configurado en GeneralSetting con fallback al historial fiscal
            $configuredSerial = trim((string) (\App\Models\GeneralSetting::first()?->fiscal_printer_serial ?? ''));
            $defaultSerial = !empty($configuredSerial)
                ? $configuredSerial
                : (string) (FiscalHistory::whereNotNull('fiscal_id')
                    ->where('fiscal_id', '!=', '')
                    ->latest('id')
                    ->value('fiscal_id') ?? '');

            if (empty($query)) {
                return response()->json([
                    'found' => false,
                    'default_serial' => $defaultSerial,
                ]);
            }

            // Normalizar número de factura (con y sin ceros a la izquierda)
            $numericOnly = ltrim(preg_replace('/[^0-9]/', '', $query), '0');
            $padded = str_pad($numericOnly ?: $query, 8, '0', STR_PAD_LEFT);

            $invoice = FiscalHistory::where('invoice_number', $query)
                ->orWhere('invoice_number', $padded)
                ->orWhere('invoice_number', $numericOnly)
                ->orWhere('invoice_number', 'like', "%{$query}%")
                ->latest('id')
                ->first();

            // Fallback a modelo Order si no se localizó directamente en FiscalHistory
            if (!$invoice && !empty($numericOnly)) {
                $order = \App\Models\Order::with(['client', 'fiscalHistories'])
                    ->where('id', $numericOnly)
                    ->orWhere('invoice_number', $query)
                    ->orWhere('invoice_number', $padded)
                    ->orWhere('invoice_number', $numericOnly)
                    ->latest('id')
                    ->first();

                if ($order) {
                    $orderFiscal = $order->fiscalHistories()->latest('id')->first();
                    $date = $orderFiscal?->invoice_date ?? $order->created_at;
                    $carbonDate = $date ? \Carbon\Carbon::parse($date) : now();

                    $rawRif = (string) ($orderFiscal?->identification ?: ($order->client?->id_number ?: 'V000000000'));
                    $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif)) ?: 'V000000000';
                    $machineSerial = !empty($configuredSerial) ? $configuredSerial : (!empty($orderFiscal?->fiscal_id) ? $orderFiscal->fiscal_id : $defaultSerial);

                    return response()->json([
                        'found' => true,
                        'default_serial' => $defaultSerial,
                        'data' => [
                            'invoice_number' => (string) ($orderFiscal?->invoice_number ?: ($order->invoice_number ?: $padded)),
                            'machine_serial' => (string) $machineSerial,
                            'invoice_date'   => $carbonDate->format('Y-m-d'),
                            'invoice_hour'   => $carbonDate->format('H:i:s'),
                            'client_name'    => (string) ($orderFiscal?->business_name ?: ($order->client?->name ?: 'CLIENTE GENERICO')),
                            'client_rif'     => (string) $cleanRif,
                            'refund_amount'  => (float) ($orderFiscal?->total_amount ?? $order->total_amount ?? 0),
                            'is_taxable'     => (float) ($orderFiscal?->iva_amount ?? $order->tax_amount ?? 0) > 0,
                            'exempt_amount'  => (float) ($orderFiscal?->exempt_amount ?? 0),
                            'taxable_amount' => (float) ($orderFiscal?->taxable_amount ?? 0),
                            'iva_amount'     => (float) ($orderFiscal?->iva_amount ?? $order->tax_amount ?? 0),
                            'spe_surcharge_amount' => (float) ($orderFiscal?->spe_surcharge_amount ?? 0),
                            'total_amount'   => (float) ($orderFiscal?->total_amount ?? $order->total_amount ?? 0),
                        ],
                    ]);
                }
            }

            if (!$invoice) {
                return response()->json([
                    'found' => false,
                    'default_serial' => $defaultSerial,
                    'message' => 'Factura no encontrada en el historial.',
                ]);
            }

            $date = $invoice->invoice_date ?? $invoice->created_at;
            $carbonDate = $date ? \Carbon\Carbon::parse($date) : now();

            $rawRif = (string) ($invoice->identification ?: 'V000000000');
            $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif)) ?: 'V000000000';
            $machineSerial = !empty($configuredSerial) ? $configuredSerial : (!empty($invoice->fiscal_id) ? $invoice->fiscal_id : $defaultSerial);

            return response()->json([
                'found' => true,
                'default_serial' => $defaultSerial,
                'data' => [
                    'invoice_number' => (string) $invoice->invoice_number,
                    'machine_serial' => (string) $machineSerial,
                    'invoice_date'   => $carbonDate->format('Y-m-d'),
                    'invoice_hour'   => $carbonDate->format('H:i:s'),
                    'client_name'    => (string) ($invoice->business_name ?: 'CLIENTE GENERICO'),
                    'client_rif'     => (string) $cleanRif,
                    'refund_amount'  => (float) $invoice->total_amount,
                    'is_taxable'     => (float) ($invoice->iva_amount ?? 0) > 0,
                    'exempt_amount'  => (float) ($invoice->exempt_amount ?? 0),
                    'taxable_amount' => (float) ($invoice->taxable_amount ?? 0),
                    'iva_amount'     => (float) ($invoice->iva_amount ?? 0),
                    'spe_surcharge_amount' => (float) ($invoice->spe_surcharge_amount ?? 0),
                    'total_amount'   => (float) $invoice->total_amount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@lookupInvoice: ' . $e->getMessage());
            return response()->json(['error' => 'Error al buscar la factura'], 500);
        }
    }

    /**
     * Check actual connectivity status of the Python fiscal bridge.
     */
    public function checkStatus()
    {
        try {
            $status = $this->service->isBridgeActive();
            return response()->json($status);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@checkStatus: ' . $e->getMessage());
            return response()->json(['is_connected' => false, 'error' => 'Error al verificar conexión'], 500);
        }
    }
}
