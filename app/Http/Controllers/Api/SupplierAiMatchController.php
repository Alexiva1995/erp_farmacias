<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ProductSupplier;

class SupplierAiMatchController extends Controller
{
    /**
     * Rechaza un match sugerido por IA.
     * Guarda el rechazo en la tabla de aprendizaje y desvincula el product_supplier.
     */
    public function reject(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'          => 'required|integer|exists:products,id',
            'product_supplier_id' => 'required|integer|exists:product_suppliers,id',
            'reason'              => 'nullable|string|max:255',
        ]);

        // Registrar el rechazo para el aprendizaje del sistema
        DB::table('supplier_ai_match_rejections')->updateOrInsert(
            [
                'product_id'          => $request->product_id,
                'product_supplier_id' => $request->product_supplier_id,
            ],
            [
                'rejected_by' => Auth::id(),
                'reason'      => $request->reason,
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        // Desvincular el product_supplier del producto (volver a null)
        ProductSupplier::where('id', $request->product_supplier_id)
            ->where('product_id', $request->product_id)
            ->where('is_ai_matched', 1) // Solo desvincula si fue la IA quien lo vinculó
            ->update([
                'product_id'    => null,
                'is_ai_matched' => 0,
            ]);

        // Contar rechazos totales: si >= 3, marcar producto como sin match posible
        $totalRechazos = DB::table('supplier_ai_match_rejections')
            ->where('product_id', $request->product_id)
            ->count();

        if ($totalRechazos >= 3) {
            DB::table('products')
                ->where('id', $request->product_id)
                ->update(['no_ai_match_possible' => true]);
        }

        return response()->json([
            'message'        => 'Match rechazado. El sistema no volverá a sugerir este emparejamiento.',
            'total_rechazos' => $totalRechazos,
            'no_match_final' => $totalRechazos >= 3,
        ]);
    }

    /**
     * Acepta manualmente un match IA (confirma la vinculación como correcta).
     */
    public function accept(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'          => 'required|integer|exists:products,id',
            'product_supplier_id' => 'required|integer|exists:product_suppliers,id',
        ]);

        // Asegurar que está vinculado correctamente
        ProductSupplier::where('id', $request->product_supplier_id)->update([
            'product_id'    => $request->product_id,
            'is_ai_matched' => 1,
        ]);

        // Eliminar cualquier rechazo previo entre estos dos (si el usuario había rechazado y luego acepta)
        DB::table('supplier_ai_match_rejections')
            ->where('product_id', $request->product_id)
            ->where('product_supplier_id', $request->product_supplier_id)
            ->delete();

        return response()->json(['message' => 'Match confirmado correctamente.']);
    }
}
