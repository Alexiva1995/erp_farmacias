<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\StoreFiscalCommandRequest;
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
            $pending = FiscalHistory::where('is_queued', true)
                ->whereNull('invoice_number')
                ->with('details')
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
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'invoice_number' => 'required',
            'fiscal_id' => 'nullable',
        ]);

        try {
            $fiscal = FiscalHistory::findOrFail($id);
            $fiscal->update([
                'invoice_number' => $request->invoice_number,
                'fiscal_id' => $request->fiscal_id,
                'invoice_date' => now(),
            ]);

            return response()->json(['message' => 'Factura confirmada exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@confirm: ' . $e->getMessage());
            return response()->json(['error' => 'Error al confirmar la impresión'], 500);
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
}
