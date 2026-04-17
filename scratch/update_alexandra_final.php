<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CashClosing;

$id = 6589;
$closing = CashClosing::find($id);

if ($closing) {
    $closing->recalculateTotals();
    echo "Cierre $id actualizado con éxito.\n";
    echo "Nuevos totales:\n";
    echo "Venta COP: " . $closing->total_cop . "\n";
    echo "Efectivo Físico: " . $closing->cop_delivered . "\n";
} else {
    echo "Cierre $id no encontrado.\n";
}
