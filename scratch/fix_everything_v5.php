<?php

use App\Models\DailyCashClosure;
use App\Models\CashClosing;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Script Maestro v5 (Consolidado)
 * -------------------------------
 * 1. Revincula la factura ID 2154 de Jackeline al cierre correcto de Marzo.
 * 2. Sincroniza arqueos individuales y diarios SOLO de Marzo 2026.
 * 3. Restaura de forma segura los arqueos de Enero/Febrero 2026 si quedaron en 0.
 */

echo "Iniciando saneamiento maestro v5...\n";

DB::transaction(function() {
    
    // --- 1. REVINCLUACIÓN QUIRÚRGICA ---
    $orderJackeline = Order::find(2154);
    if ($orderJackeline && $orderJackeline->order_date >= '2026-03-01' && $orderJackeline->order_date <= '2026-03-31') {
        // Encontramos el primer arqueo de Jackeline (81) en Marzo
        $firstMarchClosing = CashClosing::where('seller_id', 81)
            ->where('closing_date', 'like', '2026-03-%')
            ->orderBy('closing_date', 'asc')
            ->first();
            
        if ($firstMarchClosing) {
            echo "Moviendo factura 2154 ($1.89) al cierre {$firstMarchClosing->id} de Marzo.\n";
            $orderJackeline->cash_closing_id = $firstMarchClosing->id;
            $orderJackeline->save();
        }
    }

    // --- 2. SINCRONIZACIÓN DE MARZO ---
    echo "Sincronizando Arqueos de Marzo 2026...\n";
    $marchClosings = CashClosing::where('closing_date', 'like', '2026-03-%')->get();
    foreach ($marchClosings as $closing) {
        $ordersSum = $closing->orders()->where('status', 'Completed')->selectRaw('
            SUM(CASE WHEN currency = "USD" THEN total_amount ELSE 0 END) as usd,
            SUM(CASE WHEN currency = "BS" THEN total_amount ELSE 0 END) as bs,
            SUM(CASE WHEN currency = "COP" THEN total_amount ELSE 0 END) as cop,
            SUM(total_amount_usd) as usd_hist
        ')->first();
        
        $closing->total_usd = (float) $ordersSum->usd;
        $closing->total_bs  = (float) $ordersSum->bs;
        $closing->total_cop = (float) $ordersSum->cop;
        $closing->total_sales = (float) $ordersSum->usd_hist;
        $closing->save();
    }

    echo "Sincronizando Cierres Diarios de Marzo 2026...\n";
    $marchDaily = DailyCashClosure::with('cashClosings')->where('created_at', 'like', '2026-03-%')->get();
    foreach ($marchDaily as $daily) {
        $children = $daily->cashClosings;
        $daily->total_usd = (float) $children->sum('total_usd');
        $daily->total_bs  = (float) $children->sum('total_bs');
        $daily->total_cop = (float) $children->sum('total_cop');
        $daily->total_sales = (float) $children->sum('total_sales');
        $daily->save();
    }

    // --- 3. RESTAURACIÓN SEGURA DE ENERO/FEBRERO ---
    foreach (['2026-01', '2026-02'] as $period) {
        echo "Intentando restaurar cierres diarios de $period...\n";
        $periodDaily = DailyCashClosure::with('cashClosings')->where('created_at', 'like', "$period%")->get();
        foreach ($periodDaily as $daily) {
            // Solo restauramos si quedaron en 0 pero tienen hijos con datos
            if ($daily->total_sales <= 0 ) {
               $childSales = (float) $daily->cashClosings()->sum('total_sales');
               if ($childSales > 0) {
                    echo "Restaurando cierre diario ID {$daily->id} ($period).\n";
                    $daily->total_usd = (float) $daily->cashClosings()->sum('total_usd');
                    $daily->total_bs  = (float) $daily->cashClosings()->sum('total_bs');
                    $daily->total_cop = (float) $daily->cashClosings()->sum('total_cop');
                    $daily->total_sales = $childSales;
                    $daily->save();
               }
            }
        }
    }
});

echo "Saneamiento maestro v5 completado exitosamente.\n";
