<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenants = \DB::table('tenants')->get();
foreach ($tenants as $t) {
    echo "TENANT: " . $t->id . " | " . json_encode($t) . "\n";
}
