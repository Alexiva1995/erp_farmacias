<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Jobs\ProcessSupplierConnectionJob;
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

        return ApiResponse::success([
            'name' => $supplier->name,
            'id' => $supplier->id
        ]);
    }

    /**
     * Procesar la carga del archivo Excel desde el portal público.
     */
    public function upload(Request $request, $token)
    {
        $supplier = Supplier::where('public_token', $token)->first();

        if (!$supplier) {
            return ApiResponse::error('Enlace no válido.', 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'exchange_rate' => 'required|numeric|min:0.01',
        ]);

        $connection = $supplier->connections()->first();

        if (!$connection || empty($connection->structure)) {
            return ApiResponse::error('El proveedor no tiene una configuración de mapeo definida. Contacte con la farmacia.', 422);
        }

        try {
            // Guardar el archivo temporalmente
            $path = $request->file('file')->store('temp', ['disk' => 'local']);
            
            // Despachar el Job con la tasa de cambio (SÍNCRONO)
            ProcessSupplierConnectionJob::dispatchSync(
                $supplier,
                null, // No hay usuario autenticado (es público)
                $path,
                $connection->structure,
                (float) $request->exchange_rate
            );

            return ApiResponse::success(null, 'Archivo recibido correctamente. El procesamiento ha comenzado.');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al procesar el archivo: ' . $e->getMessage(), 500);
        }
    }
}
