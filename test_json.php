<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new App\Services\Bi\ProductMasterReportService(new App\Repositories\ProductMasterReportRepository());
$data = $service->getDashboardData(['start_date'=>'2026-04-01']);

echo "top_volume is array? " . (is_array($data['quadrant1']['top_volume']) ? "Yes" : "No, type is " . gettype($data['quadrant1']['top_volume'])) . "\n";
echo "top_margin is array? " . (is_array($data['quadrant1']['top_margin']) ? "Yes" : "No, type is " . gettype($data['quadrant1']['top_margin'])) . "\n";
echo "top_volume JSON: " . json_encode($data['quadrant1']['top_volume']) . "\n";
echo "lab_ranking JSON: " . json_encode($data['quadrant1']['lab_ranking']) . "\n";
