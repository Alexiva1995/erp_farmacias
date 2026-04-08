<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductSupplier;

$productId = 15097;
$product = Product::find($productId);

if (!$product) {
    echo "Producto $productId no encontrado.\n";
    exit;
}

echo "Producto: " . $product->name . " (ID: $productId)\n";

$suppliers = ProductSupplier::where('product_id', $productId)->get();

if ($suppliers->isEmpty()) {
    echo "No se encontraron entradas en product_suppliers para el producto $productId.\n";
} else {
    echo "Entradas en product_suppliers:\n";
    foreach ($suppliers as $s) {
        echo "- ID: " . $s->id . " | Proveedor ID: " . $s->supplier_id . " | Costo: " . $s->unit_cost_usd . " | Costo desc: " . $s->unit_cost_usd_with_discount . " | Deleted: " . ($s->deleted_at ? 'SI' : 'NO') . "\n";
    }
}
