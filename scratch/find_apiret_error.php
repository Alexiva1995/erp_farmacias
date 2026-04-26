<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$productId = 12352; // ID del Apiret segun captura

$errors = DB::table('order_details')
    ->select(
        'orders.id as order_id',
        'orders.order_date',
        'order_details.quantity',
        'order_details.price as sale_price',
        'order_details.unit_cost as registered_unit_cost',
        DB::raw('(order_details.quantity * order_details.unit_cost) as total_cost_in_sale')
    )
    ->join('orders', 'order_details.order_id', '=', 'orders.id')
    ->where('order_details.product_id', $productId)
    ->orderBy(DB::raw('(order_details.quantity * order_details.unit_cost)'), 'desc')
    ->limit(10)
    ->get();

echo "Buscando errores de costo para el Producto ID: $productId\n";
echo str_repeat("-", 80) . "\n";

foreach ($errors as $error) {
    echo "Factura ID: {$error->order_id} | Fecha: {$error->order_date}\n";
    echo "Cant: {$error->quantity} | Precio Venta: \${$error->sale_price} | COSTO REGISTRADO: \${$error->registered_unit_cost}\n";
    echo "TOTAL COSTO EN ESTA VENTA: \${$error->total_cost_in_sale}\n";
    echo str_repeat("-", 80) . "\n";
}

if ($errors->isEmpty()) {
    echo "No se encontraron costos > $50. Buscando cualquier anomalía...\n";
}
