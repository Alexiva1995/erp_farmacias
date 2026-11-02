<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Order\OrderQueryService::class);
$year = now()->year;

echo "=== EMPLOYEE SALES BY AMOUNT ===\n";
print_r($service->getEmployeeSalesByAmount($year)->toArray());

echo "\n=== EMPLOYEE SALES BY UNITS ===\n";
print_r($service->getEmployeeSalesByUnits($year)->toArray());


