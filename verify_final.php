<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\Product;

$invoices = Invoice::where('supplier_id', 3)->with('details.product')->get();

echo "Facturas del proveedor 3: " . $invoices->count() . "\n\n";

$withProduct = 0;
$withoutProduct = 0;
$draftProducts = 0;

foreach ($invoices as $invoice) {
    echo "Factura #{$invoice->invoice_number} - Detalles: " . $invoice->details->count() . "\n";
    foreach ($invoice->details as $detail) {
        if ($detail->product) {
            $isDraft = $detail->product->is_deleted ? ' [BORRADOR]' : '';
            echo "  - Producto: {$detail->product->name}{$isDraft} | Barcode: {$detail->product->barcode}\n";
            $withProduct++;
            if ($detail->product->is_deleted) $draftProducts++;
        } else {
            echo "  - Sin producto vinculado (product_id: {$detail->product_id})\n";
            $withoutProduct++;
        }
    }
}

echo "\n=== RESUMEN ===\n";
echo "Detalles CON producto: $withProduct\n";
echo "Detalles SIN producto: $withoutProduct\n";
echo "Productos en borrador (is_deleted): $draftProducts\n";
