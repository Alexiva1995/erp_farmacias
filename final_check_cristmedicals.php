<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\Supplier;
use App\Jobs\ProcessSupplierConnectionJob;

// 1. Limpiar facturas previas
$invoices = Invoice::where('supplier_id', 3)->get();
foreach ($invoices as $invoice) {
    $invoice->details()->delete();
    $invoice->delete();
}
echo "Limpieza completada.\n";

// 2. Sincronizar
$supplier = Supplier::find(3);
if ($supplier) {
    echo "Sincronizando proveedor: " . $supplier->name . "...\n";
    try {
        ProcessSupplierConnectionJob::dispatchSync($supplier, 1);
        echo "Sincronización finalizada.\n";
        
        $count = Invoice::where('supplier_id', 3)->count();
        echo "Total facturas creadas: $count\n";
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
