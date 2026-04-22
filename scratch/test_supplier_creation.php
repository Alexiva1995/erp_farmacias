<?php

use App\Models\Supplier;
use App\Enums\SupplierType;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    DB::beginTransaction();

    $data = [
        'type' => 'externo',
        'name' => 'Test Externo Nena',
        'social_reason' => 'Inversiones Nena C.A.',
        'rif' => 'J-12345678-9',
        'address' => 'Direccion de prueba',
        // 'payment_due_type' => null, // Esto es lo que fallaba
    ];

    // Simulamos lo que llegaría del backend con mis cambios
    $data['payment_due_type'] = 'invoice_date';
    $data['invoice_date_reference'] = 'issue_date';
    $data['payment_method'] = 'Bs';

    $supplier = Supplier::create($data);

    echo "Proveedor creado exitosamente con ID: " . $supplier->id . "\n";
    
    DB::rollBack();
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
