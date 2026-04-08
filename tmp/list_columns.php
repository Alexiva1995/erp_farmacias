<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

$columns = Schema::getColumnListing('product_suppliers');
echo "Columnas de product_suppliers:\n";
print_r($columns);
