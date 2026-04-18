<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\CashClosing;
use App\Models\DailyCashClosure;

echo "--- ANALYZING CLOSURE 6509 (Maria Martinez) ---\n";
$c = CashClosing::with('orders')->find(6509);
if ($c) {
    echo "Stored total_cop: " . $c->total_cop . "\n";
    echo "Stored cop_spare: " . $c->cop_spare . "\n";
    echo "Stored cop_delivered: " . $c->cop_delivered . "\n";
    echo "Orders sum (currency=COP): " . $c->orders()->where('currency', 'COP')->sum('total_amount') . "\n";
    echo "Total USD Performance: " . $c->total_usd . "\n";
    
    echo "\nOrders Detail:\n";
    foreach ($c->orders as $o) {
        echo "Order #{$o->id} | {$o->total_amount} {$o->currency} | USD Equiv: {$o->total_amount_usd} | Status: {$o->status}\n";
    }
}

echo "\n--- ANALYZING DAILY CLOSURES 1051, 1052 ---\n";
foreach ([1051, 1052] as $id) {
    $d = DailyCashClosure::find($id);
    if ($d) {
        echo "Daily #$id | Total USD: {$d->total_usd} | Total COP: {$d->total_cop} | USD Deliv: {$d->usd_delivered} | COP Deliv: {$d->cop_delivered}\n";
    }
}
