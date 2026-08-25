<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Jobs\ProcessSupplierConnectionJob;
use App\Http\Requests\Suppliers\SupplierPublicUploadRequest;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Storage;

class SupplierPublicUploadController extends Controller
{
    /**
     * Obtener información del proveedor mediante el token público.
     */
    public function show($token)
    {
        $supplier = Supplier::where('public_token', $token)->first();

        if (!$supplier) {
            return ApiResponse::error('Enlace no válido o expirado.', 404);
        }

        $latestExchangeRate = \App\Models\ExchangeRate::orderByDesc('created_at')
            ->where('currency_code', 'BS')
            ->first();

        return ApiResponse::success([
            'name' => $supplier->name,
            'id' => $supplier->id,
            'last_upload' => $supplier->connections()->first()?->last_connection,
            'exchange_rate' => $latestExchangeRate ? (float) $latestExchangeRate->rate : null,
        ]);
    }

    /**
     * Procesar la carga del archivo Excel desde el portal público.
     */
    public function upload(SupplierPublicUploadRequest $request, $token)
    {
        $supplier = Supplier::where('public_token', $token)->first();

        if (!$supplier) {
            return ApiResponse::error('Enlace no válido.', 404);
        }

        $connection = $supplier->connections()->first();

        if (!$connection || empty($connection->structure)) {
            return ApiResponse::error('El proveedor no tiene una configuración de mapeo definida. Contacte con la farmacia.', 422);
        }

        try {
            $filePaths = [];

            if ($request->hasFile('file')) {
                $filePaths[] = $request->file('file')->store('temp', ['disk' => 'local']);
            }

            if ($request->hasFile('file_2')) {
                $filePaths[] = $request->file('file_2')->store('temp', ['disk' => 'local']);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $f) {
                    if ($f) {
                        $filePaths[] = $f->store('temp', ['disk' => 'local']);
                    }
                }
            }

            if (empty($filePaths)) {
                return ApiResponse::error('No se ha recibido ningún archivo.', 422);
            }
            
            // Despachar el Job con la tasa de cambio y archivos (SÍNCRONO)
            ProcessSupplierConnectionJob::dispatchSync(
                $supplier,
                null, // No hay usuario autenticado (es público)
                count($filePaths) === 1 ? $filePaths[0] : $filePaths,
                $connection->structure,
                (float) $request->exchange_rate
            );

            return ApiResponse::success(null, 'Archivo(s) recibido(s) correctamente. El procesamiento ha finalizado.');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al procesar el archivo: ' . $e->getMessage(), 500);
        }
    }
}
