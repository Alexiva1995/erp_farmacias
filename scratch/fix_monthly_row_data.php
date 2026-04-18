<?php

use App\Models\DailyCashClosure;
use App\Models\CashClosing;
use Illuminate\Support\Facades\DB;

/**
 * Script de Saneamiento Profundo (Nivel Daily)
 * -------------------------------------------
 * Asegura que cada 'DailyCashClosure' sea la suma exacta de sus hijos 'CashClosing'.
 * Esto corrige cualquier monto negativo en COP o BS que aparezca en el resumen mensual.
 */

echo "Iniciando saneamiento profundo de cierres diarios...\n";

DB::transaction(function() {
    $dailyClosures = DailyCashClosure::with('cashClosings')->get();
    
    foreach ($dailyClosures as $daily) {
        $children = $daily->cashClosings;
        
        // Calculamos los totales reales basados en los vendedores (que ya están sincronizados con órdenes)
        $newUsd   = (float) $children->sum('total_usd');
        $newBs    = (float) $children->sum('total_bs');
        $newCop   = (float) $children->sum('total_cop');
        $newSales = (float) $children->sum('total_sales');

        // Otros campos físicos si es necesario (delivered)
        $newUsdDelivered = (float) $children->sum('usd_delivered');
        $newCopDelivered = (float) $children->sum('cop_delivered');
        $newBsDelivered  = (float) $children->sum('bs_delivered');

        $daily->total_usd = $newUsd;
        $daily->total_bs  = $newBs;
        $daily->total_cop = $newCop;
        $daily->total_sales = $newSales;
        
        $daily->usd_delivered = $newUsdDelivered;
        $daily->cop_delivered = $newCopDelivered;
        $daily->bs_delivered  = $newBsDelivered;

        $daily->save();
    }
});

echo "Saneamiento completado. Los totales diarios ahora son la suma fiel de los cierres de vendedores.\n";
