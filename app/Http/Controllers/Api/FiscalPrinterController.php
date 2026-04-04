<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalHistory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FiscalPrinterController extends Controller
{
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
            'fiscal_id' => 'required',
        ]);

        try {
            $fiscal = FiscalHistory::findOrFail($id);
            $fiscal->update([
                'invoice_number' => $request->invoice_number,
                'fiscal_id' => $request->fiscal_id,
                'invoice_date' => now(), // Actualizamos la fecha a la real de impresión
            ]);

            return response()->json(['message' => 'Factura confirmada exitosamente', 'data' => $fiscal]);
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
                // Si no existe, podríamos intentar crearla aquí o devolver error.
                // Según OrderActionService, ya debería existir si se completó la orden.
                return response()->json(['error' => 'No se encontró registro fiscal para esta orden'], 404);
            }

            $fiscal->update(['is_queued' => true]);

            return response()->json(['message' => 'Orden encolada para impresión fiscal']);
        } catch (\Exception $e) {
            Log::error('Error en FiscalPrinterController@queue: ' . $e->getMessage());
            return response()->json(['error' => 'Error al encolar la orden'], 500);
        }
    }
}
