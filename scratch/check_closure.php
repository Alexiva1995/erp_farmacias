<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DailyCashClosure;

use App\Models\CashClosing;

use App\Models\Order;
use App\Models\Expense;
use App\Models\CashFlow;

$closure = DailyCashClosure::find(1067);
foreach ($closure->cashClosings as $cc) {
    echo "ID: " . $cc->id . " | COP Conversion: " . $cc->cop_conversion . " | USD Conversion: " . $cc->usd_conversion . "\n";
}

$expenses = Expense::whereDate('created_at', '2026-04-30')->get();
foreach ($expenses as $e) {
    echo "Expense ID: " . $e->id . " | Amount: " . $e->amount . " | Currency: " . $e->currency . " | Desc: " . $e->description . "\n";
}
