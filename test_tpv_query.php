<?php

// Arrancar Laravel
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Order\OrderQueryService;
use Illuminate\Http\Request;

$svc = app(OrderQueryService::class);

$request = Request::create('/api/tpv/order', 'GET', [
    'page' => '1',
    'itemsPerPage' => '10',
    'sortBy' => 'valid_stock',
    'orderBy' => 'desc',
    'isStrictSearch' => 'false',
]);

try {
    // Query para datos (con ORDER BY)
    $dataQuery = $svc->getFilteredQueryProduct($request);
    
    // Query para conteo (sin ORDER BY - tal como lo hace el controlador actualizado)
    $countQuery = $svc->getCountQueryProduct($request);
    $total = $countQuery->count();

    $items = $dataQuery->skip(0)->take(3)->get();

    echo "total: $total" . PHP_EOL;
    echo "items: " . count($items) . PHP_EOL;
    foreach ($items as $item) {
        echo "  [{$item->item_type}] {$item->name} stock={$item->valid_stock_sum}" . PHP_EOL;
    }
    echo PHP_EOL . "OK - El endpoint funciona" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
