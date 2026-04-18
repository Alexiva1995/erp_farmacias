<?php

use App\Models\DailyCashClosure;
use App\Models\CashClosing;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Script de Reparación EXCLUSIVO PARA MARZO 2026
 */

echo "Iniciando reparación segura de MARZO 2026...\n";

DB::transaction(function() {
    
    // 1. Corregir Jackeline (Factura 2154) - Moverla a su primer cierre de Marzo
    $orderJackeline = Order::find(2154);
    if ($orderJackeline && $orderJackeline->order_date >= '2026-03-01' && $orderJackeline->order_date <= '2026-03-31') {
        $firstMarchClosing = CashClosing::where('seller_id', 81)
            ->where('closing_date', 'like', '2026-03-%')
            ->orderBy('closing_date', 'asc')
            ->first();
            
        if ($firstMarchClosing) {
            echo "Ajustando factura de Jackeline ($1.89) en Marzo.\n";
            $orderJackeline->cash_closing_id = $firstMarchClosing->id;
            $orderJackeline->save();
        }
    }

    // 2. Sincronizar Arqueos SOLO de Marzo 2026
    echo "Sincronizando arqueos únicamente de marzo...\n";
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

    // 3. Sincronizar Resúmenes Diarios SOLO de Marzo 2026
    echo "Nivelando resúmenes diarios únicamente de marzo...\n";
    $marchDaily = DailyCashClosure::with('cashClosings')->where('created_at', 'like', '2026-03-%')->get();
    foreach ($marchDaily as $daily) {
        $children = $daily->cashClosings;
        $daily->total_usd = (float) $children->sum('total_usd');
        $daily->total_bs  = (float) $children->sum('total_bs');
        $daily->total_cop = (float) $children->sum('total_cop');
        $daily->total_sales = (float) $children->sum('total_sales');
        $daily->save();
    }
});

echo "¡Reparación de Marzo 2026 completada exitosamente! Los otros meses no fueron tocados.\n";
