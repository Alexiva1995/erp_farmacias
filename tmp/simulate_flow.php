<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Repository\ProductSupplierRepository;
use App\Services\ProductSupplierServices;
use Illuminate\Database\Eloquent\Collection;

$productId = 15097;
$product = Product::find($productId);
$collection = new Collection([$product]);

$repo = new ProductSupplierRepository();
$service = app(ProductSupplierServices::class);

$conDescuento = "false"; // Probemos con el caso mas comun de error

echo "1. Llamando a getSupplierToReplenishTheProducts en el Repo...\n";
$itemsWithSuppliers = $repo->getSupplierToReplenishTheProducts($collection, $conDescuento);

if (empty($itemsWithSuppliers)) {
    echo "ERROR: getSupplierToReplenishTheProducts devolvió array vacío.\n";
} else {
    $item = $itemsWithSuppliers[0];
    echo "Oferta encontrada: " . ($item['productSupplier'] ? "SI (ID: " . $item['productSupplier']->id . ")" : "NO") . "\n";
    echo "Proveedor encontrado: " . ($item['supplier'] ? "SI (Name: " . $item['supplier']->name . ")" : "NO") . "\n";
    
    echo "2. Llamando a checkTolerance en el Service...\n";
    $finalItems = $service->checkTolerance($itemsWithSuppliers, $conDescuento);
    
    $finalItem = $finalItems[0];
    echo "Precio final tras tolerancia: " . $finalItem['precio_final_supplier'] . "\n";
    echo "Porcentaje: " . $finalItem['percentageIncrease'] . "\n";
}
