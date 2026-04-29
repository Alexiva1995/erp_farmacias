<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;

try {
    $conn = SupplierConnection::where('supplier_id', 3)->first();
    $service = app(SupplierConnectionService::class);
    
    echo "Fetching data for supplier " . $conn->supplier_id . "...\n";
    $data = $service->fetchData($conn);

    echo "Resultados finales:\n";
    echo "- Productos: " . count($data['products'] ?? []) . "\n";
    echo "- Facturas: " . count($data['invoices'] ?? []) . "\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
