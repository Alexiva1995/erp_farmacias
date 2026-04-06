<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$repo = app(\App\Repository\ProductRepository::class);
$filtros = [
    "tipo_de_filtracion" => "combinado",
    "tipo_vista"         => false,
    "lapso_de_tiempo" => "1 month",
    "stock"           => "fallas",
    "ids_in"          => [1,2,3,4,5] // Dummy list
];

$builder = $repo->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);
echo "AVERAGE BUILDER:\n";
echo $builder->toSql() . "\n";
echo "BINDINGS: " . json_encode($builder->getBindings()) . "\n\n";

$builder2 = $repo->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);
echo "SALES BUILDER:\n";
echo $builder2->toSql() . "\n";
echo "BINDINGS: " . json_encode($builder2->getBindings()) . "\n";
