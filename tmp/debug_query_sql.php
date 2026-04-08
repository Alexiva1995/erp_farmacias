<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$productId = 15097;
$products = Product::where('id', $productId)->get();
$productIds = [$productId];

$conDescuento = "true"; // Probemos con true

$query = DB::table('product_suppliers')
    ->whereIn('product_id', $productIds)
    ->whereNull('deleted_at')
    ->where(function ($query) {
        $query->where('unit_cost_usd', '>', 0)
            ->orWhere('unit_cost_usd_with_discount', '>', 0);
    });

if ($conDescuento == "true") {
    $query->orderBy("unit_cost_usd_with_discount", "ASC");
} else {
    $query->orderBy("unit_cost_usd", "ASC");
}

$bestOffersData = $query->get();

echo "Resultados de la consulta SQL:\n";
foreach ($bestOffersData as $row) {
    echo "- ID: " . $row->id . " | Producto ID: " . $row->product_id . " | Precio: " . ($conDescuento == "true" ? $row->unit_cost_usd_with_discount : $row->unit_cost_usd) . "\n";
}

if ($bestOffersData->isEmpty()) {
    echo "CONSULTA VACIA\n";
}
