<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
use Illuminate\Contracts\Console\Kernel;
$app->make(Kernel::class)->bootstrap();

$service = new \App\Services\Identity\CNEQueryService();
$res = $service->search('24150980');
print_r($res);
