<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repo = new App\Repositories\ProductMasterReportRepository();
$data = $repo->getLaboratoryRanking(['start_date'=>'2026-04-01']);
echo json_encode($data);
