<?php

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use Illuminate\Support\Facades\DB;

/**
 * Script de Sincronización Histórica (Consolidado vs Productividad)
 * -----------------------------------------------------------------
 * Sincroniza el campo 'total_sales' de los arqueos de caja con la suma
 * real del campo 'total_amount_usd' de las facturas que contienen.
 */

echo "Iniciando sincronización histórica de ventas...\n";

DB::transaction(function() {
    echo "Fase 1: Recalculando total_sales por vendedor basándose en Facturas (USD Histórico)...\n";
    
    $closings = CashClosing::with('orders')->get();
    
    foreach ($closings as $closing) {
        // Sumamos el valor en dólares registrado en el momento de cada venta completada
        $historicalUsdTotal = (float) $closing->orders()
            ->where('status', 'Completed')
            ->sum('total_amount_usd');
            
        // Si hay saldos a favor (spare) en COP/BS que no son de órdenes, 
        // podrías sumarlos aquí si fuera necesario, pero para coincidir con productividad
        // lo ideal es basarse estrictamente en el total_amount_usd de las órdenes.
        
        $newTotalSales = round($historicalUsdTotal, 2);

        if ($closing->total_sales != $newTotalSales) {
            $closing->total_sales = $newTotalSales;
            $closing->save();
        }
    }

    echo "Fase 2: Sincronizando Totales de Cierres Diarios...\n";
    
    $dailyClosures = DailyCashClosure::all();
    foreach ($dailyClosures as $daily) {
        $children = $daily->cashClosings;
        
        // Sincronizamos el total_sales del día con la nueva suma histórica de los vendedores
        $sumSales = $children->sum('total_sales');

        if ($daily->total_sales != $sumSales) {
            $daily->total_sales = $sumSales;
            $daily->save();
        }
    }
});

echo "--------------------------------------------------\n";
echo "Sincronización histórica completada con éxito.\n";
echo "Los reportes de Productividad y Consolidado ahora son consistentes.\n";
