<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\CashClosing;

$c = CashClosing::with('orders')->find(6509);
if (!$c) {
    echo "Closure 6509 not found\n";
    exit;
}

echo "Closure 6509 Analysis:\n";
echo "Total COP (stored): " . $c->total_cop . "\n";
echo "Total COP (calculated from orders): " . $c->orders()->sum('total_cop') . "\n";
echo "COP Spare: " . $c->cop_spare . "\n";
echo "Total USD Performance: " . $c->total_usd . "\n";
echo "Order Details:\n";
foreach ($c->orders as $o) {
    echo "ID: " . $o->id . " | Total: " . $o->total . " | Currency: " . $o->payment_currency . " | Total COP (from order): " . $o->total_cop . " | Total Pay: " . $o->total_pay . " | Change COP: " . $o->change_cop . "\n";
}
