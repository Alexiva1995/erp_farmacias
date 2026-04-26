<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new App\Services\Bi\ProductMasterReportService(new App\Repositories\ProductMasterReportRepository());
$data = $service->getDashboardData(['start_date'=>'2026-04-01']);

echo "top_margin JSON: " . json_encode($data['quadrant1']['top_margin']) . "\n";
