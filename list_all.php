<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (App\Models\Employee::all(['name', 'last_name', 'identification']) as $e) {
    echo $e->name . ' ' . $e->last_name . ' | ' . $e->identification . PHP_EOL;
}
