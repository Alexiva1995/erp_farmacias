<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

function inspectOrder($id) {
    $o = Order::with('details.product')->find($id);
    if (!$o) {
        echo "Order $id not found.\n";
        return;
    }
    echo "--- Orden $id ---\n";
    echo "Total Factura: " . number_format($o->total_amount, 2) . " " . $o->currency . "\n";
    echo "Vueltos (money_returns): " . number_format($o->money_returns, 2) . "\n";
    echo "Metodos de Pago:\n";
    print_r($o->payment_methods);
    
    echo "Detalles del Carrito:\n";
    foreach ($o->details as $d) {
        echo "   - Producto: " . ($d->product->name ?? 'N/A') . " (ID: {$d->product_id})\n";
        echo "     Cantidad: {$d->quantity} | Precio Línea: " . number_format($d->price, 2) . " | Unit: " . number_format($d->price / $d->quantity, 2) . "\n";
    }
    echo "\n";
}

inspectOrder(113394);
inspectOrder(113395);
