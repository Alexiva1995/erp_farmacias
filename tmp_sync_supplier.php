<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Supplier;
use App\Jobs\ProcessSupplierConnectionJob;

$supplier = Supplier::find(3);
if ($supplier) {
    echo "Sincronizando proveedor: " . $supplier->name . "...\n";
    ProcessSupplierConnectionJob::dispatchSync($supplier, 1);
    echo "Sincronización completada.\n";
} else {
    echo "Proveedor no encontrado.\n";
}
