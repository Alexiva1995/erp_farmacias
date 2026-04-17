<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\DailyCashClosure;

$ids = [1051, 1052];
foreach ($ids as $id) {
    $d = DailyCashClosure::find($id);
    if ($d) {
        echo "ID: $id | Venta USD: {$d->total_usd} | Entrega USD: {$d->usd_delivered} | Venta COP: {$d->total_cop} | Entrega COP: {$d->cop_delivered}\n";
    }
}
