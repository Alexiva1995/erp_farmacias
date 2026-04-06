<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

// Total inventario (no eliminados)
$total = Product::count();

// Redundantes
$redundantes = Product::where('is_scarce', true)->count();

// No redundantes (debería = total del asistente en "Todos")
$no_redundantes = Product::where('is_scarce', false)->count();

// Colombianos no redundantes (los que el bug excluía)
$colombianos_no_redundantes = Product::where('is_scarce', false)
    ->where('is_colombian_origin', 1)->count();

// No colombianos no redundantes (los que el bug mostraba)
$no_colombianos_no_redundantes = Product::where('is_scarce', false)
    ->where('is_colombian_origin', 0)->count();

echo "=== Diagnóstico de Productos ===\n";
echo "Total inventario: $total\n";
echo "Redundantes (is_scarce=1): $redundantes\n";
echo "No redundantes (is_scarce=0): $no_redundantes\n";
echo "  -> Colombianos: $colombianos_no_redundantes\n";
echo "  -> No colombianos: $no_colombianos_no_redundantes\n";
echo "---\n";
echo "Bug: El asistente sin Colombia solo mostraría: $no_colombianos_no_redundantes (debería ser $no_redundantes)\n";
echo "Diferencia causada por bug: {$colombianos_no_redundantes}\n";
