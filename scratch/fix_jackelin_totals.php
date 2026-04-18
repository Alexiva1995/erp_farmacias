<?php

use App\Models\CashClosing;
use Illuminate\Support\Facades\DB;

/**
 * Script de Reparación Contable - Jackelinvarela
 * Propósito: Corregir cierres con USD inflado y COP negativo (como el ID 6202).
 */

echo "Iniciando reparación de cierres...\n";

// IDs identificados con anomalías masivas
$targetIds = [6202]; 

// También buscamos otros que tengan la misma lógica de error (USD > 10k o COP < 0)
$anomalousClosings = CashClosing::where('total_usd', '>', 5000)
    ->orWhere('total_cop', '<', 0)
    ->get();

foreach ($anomalousClosings as $closing) {
    echo "------------------------------------------\n";
    echo "Procesando Cierre ID: {$closing->id} (Seller: {$closing->seller->username})\n";
    echo "Valores ANTES -> USD: {$closing->total_usd} | COP: {$closing->total_cop} | BS: {$closing->total_bs} | Venta: {$closing->total_sales}\n";

    DB::transaction(function() use ($closing) {
        // En este sistema, si hubo una confusión de divisas en las órdenes, 
        // recalculateTotals() restaurará el balance real basándose en la moneda de cada orden.
        $closing->recalculateTotals();
    });

    $closing->refresh();
    echo "Valores DESPUÉS -> USD: {$closing->total_usd} | COP: {$closing->total_cop} | BS: {$closing->total_bs} | Venta: {$closing->total_sales}\n";
    
    if ($closing->total_cop < 0) {
        echo "¡ATENCIÓN! El balance de COP sigue siendo negativo. Revisando cop_spare...\n";
        if ($closing->cop_spare < 0) {
            echo "Corrigiendo cop_spare negativo de {$closing->cop_spare} a 0...\n";
            $closing->cop_spare = 0;
            $closing->recalculateTotals();
            echo "Nuevo Total COP: {$closing->total_cop}\n";
        }
    }
}

echo "------------------------------------------\n";
echo "Reparación completada.\n";
