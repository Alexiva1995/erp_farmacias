<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$r = app()->make(\App\Repository\ProductRepository::class);
$q = $r->builerFiltrarProductForIaOrderAssistantTypeAverage([
    'tipo_filtracion' => 'combinado',
    'lapso_de_tiempo' => '1 Mes',
    'stock' => 'fallas',
    'previousDate' => '2026-02-04',
    'dateToday' => '2026-03-04'
]);

echo $q->toSql();
echo "\n\nBindings:\n";
print_r($q->getBindings());
