<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$service = app(\App\Services\Reports\IaAssistantReportService::class);
$filtros = [
    "tipo_de_filtracion" => "combinado",
    "tipo_vista"         => false,
    "lapso_de_tiempo" => "1 month",
    "laboratoryId"    => [],
    "groups"          => [],
    "stock"           => "fallas",
    "q"               => "",
    "isColombian"     => false
];

$allIds = $service->getFilteredIds($filtros, false);
echo "Total IDs from service: " . count($allIds) . "\n";

$report = $service->getFilteredReportWithoutPaginate($filtros);
echo "Total items populated: " . count($report) . "\n";

if(count($allIds) !== count($report)) {
    echo "DISCREPANCY DETECTED!\n";
    $reportIds = array_column(json_decode(json_encode($report), true), 'id');
    $missingIds = array_diff($allIds, $reportIds);
    echo "Missing IDs: " . implode(', ', $missingIds) . "\n";
}
