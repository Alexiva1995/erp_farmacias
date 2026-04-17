<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\CashClosing;

$id = 6589;
$orders = Order::where('cash_closing_id', $id)->get();

echo "Auditoría de órdenes para Cierre $id:\n";
echo "--------------------------------------\n";

$sumCOPOrders = 0;
$sumCOPPays = 0;

foreach ($orders as $order) {
    echo "Orden ID: {$order->id} | Estado: {$order->status} | Moneda: {$order->currency} | Total: " . number_format($order->total_amount, 0) . " | Vueltos: " . number_format($order->money_returns, 0) . "\n";
    
    if ($order->currency === 'COP') {
        $sumCOPOrders += $order->total_amount;
    }
    
    // Sumar específicamente los pagos registrados como cash_cop en sus payment_methods
    $payments = $order->payment_methods ?? [];
    foreach ($payments as $p) {
        if (($p['method'] ?? '') === 'cash_cop') {
            $netPay = (float)$p['amount'] - (float)$order->money_returns;
            $sumCOPPays += $netPay;
            echo "   -> Pago COP Neto: " . number_format($netPay, 0) . " (Bruto: {$p['amount']}, Vuelto: {$order->money_returns})\n";
        }
    }
}

echo "--------------------------------------\n";
echo "Suma de Totales de Órdenes en COP: " . number_format($sumCOPOrders, 0) . "\n";
echo "Suma de Pagos Netos en COP (cash_cop): " . number_format($sumCOPPays, 0) . "\n";
echo "Valor en cop_cash (DB): " . number_format(CashClosing::find($id)->cop_cash, 0) . "\n";
