<?php

use App\Models\Supplier;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use App\Services\Suppliers\SupplierQueryService;

// Buscar un proveedor de prueba
$supplier = Supplier::find(2); // Drocerca
if (!$supplier) {
    echo "No hay proveedores disponibles.\n";
    exit;
}

echo "Usando proveedor: {$supplier->name} (ID: {$supplier->id})\n";

// 1. Preparación: Limpiar y crear datos de prueba
DB::table('product_suppliers')->where('supplier_id', $supplier->id)->delete();

// Crear 2 productos normales
$p1 = ProductSupplier::create([
    'supplier_id' => $supplier->id,
    'product_id' => 1,
    'name' => 'Producto 1 - Borrable',
    'unit_cost' => 10,
    'unit_cost_usd' => 1,
    'connection_date' => now(),
]);

$p2 = ProductSupplier::create([
    'supplier_id' => $supplier->id,
    'product_id' => 2,
    'name' => 'Producto 2 - Protegido por AutoOrden',
    'unit_cost' => 20,
    'unit_cost_usd' => 2,
    'connection_date' => now(),
]);

// Simular que p2 está en una auto-orden pendiente
DB::table('auto_order_details')->insert([
    'product_suppliers_id' => $p2->id,
    'order_id' => 1, 
    'quantity' => 5,
    'unit_cost' => 20,
    'subtotal' => 100,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "Datos de prueba creados.\n";
echo "Productos antes de la carga: " . ProductSupplier::where('supplier_id', $supplier->id)->count() . "\n";

// 2. Ejecución: Simular carga con 1 producto nuevo (Producto 3)
$data = [
    'products' => [
        [
            'product_id' => 3,
            'name' => 'Producto 3 - Nuevo',
            'unit_cost' => 30,
            'unit_cost_usd' => 3,
            'supplier_id' => $supplier->id,
            'connection_date' => now(),
        ]
    ],
    'invoices' => []
];

$service = app(SupplierQueryService::class);
$service->storeSupplierConnectionData($supplier, $data);

// 3. Validación
echo "\n--- RESULTADOS ---\n";
$afterCount = ProductSupplier::where('supplier_id', $supplier->id)->count();
echo "Productos tras la carga: {$afterCount}\n";

$existsP1 = ProductSupplier::find($p1->id);
$existsP2 = ProductSupplier::find($p2->id);

if (!$existsP1) {
    echo "✅ Producto 1 fue eliminado correctamente.\n";
} else {
    echo "❌ Producto 1 sigue existiendo.\n";
}

if ($existsP2) {
    echo "✅ Producto 2 fue PROTEGIDO correctamente (por estar en auto-orden).\n";
} else {
    echo "❌ Producto 2 fue eliminado a pesar de estar en auto-orden.\n";
}

$existsP3 = ProductSupplier::where('supplier_id', $supplier->id)->where('product_id', 3)->exists();
if ($existsP3) {
    echo "✅ Producto 3 fue insertado correctamente.\n";
} else {
    echo "❌ Producto 3 no fue insertado.\n";
}

// Limpiar auto_order_details de prueba
DB::table('auto_order_details')->where('product_suppliers_id', $p2->id)->delete();
