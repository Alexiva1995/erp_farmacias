<?php
$service = app(\App\Services\Reports\IaAssistantReportService::class);
$filtros = [
    "tipo_de_filtracion" => "combinado",
    "tipo_vista"         => true,
    "lapso_de_tiempo" => "1 month",
    "laboratoryId"    => [],
    "groups"          => [2], // Dummy group ID that exists in DB, or try 1
    "stock"           => "fallas",
    "q"               => "",
    "isColombian"     => false,
    "page"            => 1,
    "itemsPerPage"    => 25
];

try {
    $report = $service->getFilteredReport($filtros);
    echo "Groups populated: " . count($report['grupos']) . "\n";
} catch (\Exception $e) {
    echo "ERROR CAUGHT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
