<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$id = 6589;
$orders = Order::where('cash_closing_id', $id)->get();

echo "Auditoría Completa de Órdenes para Cierre $id:\n";
echo "------------------------------------------------\n";

$totals = [
    'Completed' => ['COP' => 0, 'USD' => 0, 'BS' => 0],
    'Cancelled' => ['COP' => 0, 'USD' => 0, 'BS' => 0],
    'Abandoned' => ['COP' => 0, 'USD' => 0, 'BS' => 0],
];

foreach ($orders as $order) {
    if (isset($totals[$order->status])) {
        $currency = $order->currency ?? 'BS';
        $totals[$order->status][$currency] += (float)$order->total_amount;
    }
}

foreach ($totals as $status => $currencies) {
    echo "Status: $status\n";
    foreach ($currencies as $curr => $total) {
        if ($total > 0) {
            echo "   $curr: " . number_format($total, 2) . "\n";
        }
    }
}
