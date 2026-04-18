<?php

use App\Models\CashClosing;
use App\Models\DailyCashClosure;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;

/**
 * Script de Nivelación Masiva de Saldos Contables
 * --------------------------------------------
 * 1. Recalcula 'total_sales' de cada CashClosing para que coincida con la suma de sus partes.
 * 2. Recalcula los totales de cada DailyCashClosure basándose en sus CashClosing vinculados.
 */

echo "Iniciando nivelación masiva de saldos...\n";

// Obtener tasas actuales para normalización consistente
$rates = ExchangeRate::pluck('rate', 'currency_code');
$bsRate  = (float)($rates['EUR'] ?? 1);
$copRate = (float)($rates['COP'] ?? 1);

DB::transaction(function() use ($bsRate, $copRate) {
    echo "Fase 1: Sincronizando CashClosings (Vendedores)...\n";
    $closings = CashClosing::all();
    foreach ($closings as $closing) {
        $usd = (float) $closing->total_usd;
        $bs  = (float) $closing->total_bs;
        $cop = (float) $closing->total_cop;

        $bsInUsd  = $bsRate  > 0 ? $bs  / $bsRate  : 0;
        $copInUsd = $copRate > 0 ? $cop / $copRate : 0;
        
        $newTotalSales = round($usd + $bsInUsd + $copInUsd, 2);

        if ($closing->total_sales != $newTotalSales) {
            $closing->total_sales = $newTotalSales;
            $closing->save();
        }
    }
    echo "Fase 2: Sincronizando DailyCashClosures (Global)...\n";
    $dailyClosures = DailyCashClosure::all();
    foreach ($dailyClosures as $daily) {
        $children = $daily->cashClosings;
        
        $sumUsd   = $children->sum('total_usd');
        $sumCop   = $children->sum('total_cop');
        $sumBs    = $children->sum('total_bs');
        $sumSales = $children->sum('total_sales');

        $daily->total_usd   = $sumUsd;
        $daily->total_cop   = $sumCop;
        $daily->total_bs    = $sumBs;
        $daily->total_sales = $sumSales;
        $daily->save();
    }
});

echo "------------------------------------------\n";
echo "Nivelación completada con éxito.\n";
