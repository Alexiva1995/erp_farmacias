<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InventoryMovement;

$startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
$endOfMonth = \Carbon\Carbon::now()->endOfMonth()->toDateString();

$movements = InventoryMovement::where('movement_type', 'sale')
    ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
    ->get();

echo "Movements count: " . $movements->count() . "\n";
$nonNull = 0;
foreach ($movements as $m) {
    if ($m->product_lot_id !== null) {
        $nonNull++;
    }
}
echo "Non-null product_lot_id count: $nonNull\n";
if ($nonNull > 0) {
    $first = $movements->firstWhere('product_lot_id', '!=', null);
    echo "First non-null: ID {$first->id}, Lot ID {$first->product_lot_id}\n";
}
