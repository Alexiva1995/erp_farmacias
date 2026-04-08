<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Collection;

$p = Product::find(15097);
$p->setAttribute('best_supplier_price', 7.89);
$p->setAttribute('best_supplier_percentage', -15.5);

echo "JSON output:\n";
echo json_encode($p) . "\n\n";

echo "Array output via toArray():\n";
print_r($p->toArray());
