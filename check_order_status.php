<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICANDO STATUS DE ÓRDENES ===\n\n";

// Verificar status de órdenes
$statuses = \App\Models\Order::select('status')->distinct()->pluck('status');
echo "Status disponibles en la base de datos:\n";
foreach ($statuses as $status) {
    echo "- '$status'\n";
}

echo "\n=== VERIFICANDO ÓRDENES CON DATOS ===\n\n";

// Verificar órdenes con datos
$orders = \App\Models\Order::with('details')->get();
echo "Total de órdenes: " . $orders->count() . "\n";

foreach ($orders as $order) {
    echo "Orden ID: {$order->id}, Status: '{$order->status}', Total: {$order->total_amount}, Fecha: {$order->order_date}\n";
    if ($order->details->count() > 0) {
        echo "  - Detalles: " . $order->details->count() . " productos\n";
    }
}

echo "\n=== VERIFICANDO GASTOS ===\n\n";

// Verificar gastos
$expenses = \App\Models\Expense::with('category')->get();
echo "Total de gastos: " . $expenses->count() . "\n";

foreach ($expenses as $expense) {
    echo "Gasto ID: {$expense->id}, Monto: {$expense->amount}, Fecha: {$expense->expense_date}, Categoría: " . ($expense->category->name ?? 'Sin categoría') . "\n";
}
