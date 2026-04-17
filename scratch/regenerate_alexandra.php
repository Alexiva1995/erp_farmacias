<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CashClosing;

$id = 6589;
$closing = CashClosing::find($id);

if (!$closing) {
    die("Cierre $id no encontrado.\n");
}

echo "--- DATOS ANTES DE REGENERAR ---\n";
echo "Venta COP: " . number_format($closing->total_cop, 0) . "\n";
echo "Efectivo Físico (cop_delivered): " . number_format($closing->cop_delivered, 0) . "\n";
echo "cop_cash: " . number_format($closing->cop_cash, 0) . "\n";
echo "cop_conversion: " . number_format($closing->cop_conversion, 0) . "\n";

// Ejecutamos la nueva lógica
$closing->recalculateTotals();

echo "\n--- DATOS DESPUÉS DE REGENERAR (Nueva Lógica) ---\n";
echo "Venta COP: " . number_format($closing->total_cop, 0) . " (Ahora refleja el ingreso neto de ventas)\n";
echo "Efectivo Físico (cop_delivered): " . number_format($closing->cop_delivered, 0) . " (Dinero real en caja tras vueltos)\n";
echo "cop_cash (Neto Ventas COP): " . number_format($closing->cop_cash, 0) . "\n";
echo "cop_conversion (Vueltos COP p/ USD): " . number_format($closing->cop_conversion, 0) . "\n";

// Si el usuario agregara un sobrante de 3,600 (para compensar el vuelto cruzado si así lo desea)
$sobrante = 3600;
echo "\n--- SIMULACIÓN CON SOBRANTE de 3,600 ---\n";
echo "Total a entregar (Efectivo + Sobrante): " . number_format($closing->cop_delivered + $sobrante, 0) . "\n";
