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

$p = App\Models\Product::find(12358);
echo "PRODUCT 12358:" . PHP_EOL;
echo json_encode($p ? $p->toArray() : 'not found', JSON_PRETTY_PRINT) . PHP_EOL;

$rates = DB::table('exchange_rates')->get();
echo "EXCHANGE RATES:" . PHP_EOL;
echo json_encode($rates, JSON_PRETTY_PRINT) . PHP_EOL;

$generalSettings = DB::table('general_settings')->first();
echo "GENERAL SETTINGS:" . PHP_EOL;
echo json_encode($generalSettings, JSON_PRETTY_PRINT) . PHP_EOL;

