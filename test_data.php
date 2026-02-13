<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

echo "Verificando datos en la base de datos...\n";

$orders = DB::table('orders')
    ->whereMonth('order_date', 1)
    ->whereYear('order_date', 2025)
    ->whereIn('status', ['Completed', 'Closed'])
    ->get();

echo "Total órdenes Enero 2025: " . $orders->count() . "\n";
echo "Total ventas USD: " . $orders->sum('total_amount_usd') . "\n";

$details = DB::table('order_details')
    ->whereIn('order_id', $orders->pluck('id'))
    ->get();

echo "Total unidades: " . $details->sum('quantity') . "\n";

// Verificar si hay datos de cualquier mes
$allOrders = DB::table('orders')
    ->whereIn('status', ['Completed', 'Closed'])
    ->limit(5)
    ->get();

echo "\nMuestras de todas las órdenes:\n";
foreach ($allOrders as $order) {
    echo "ID: {$order->id}, Seller: {$order->seller_id}, Monto: {$order->total_amount_usd}, Fecha: {$order->order_date}\n";
}
