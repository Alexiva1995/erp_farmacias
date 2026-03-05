<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app()->make(App\Http\Controllers\Api\ExpensesController::class);
$request = new \Illuminate\Http\Request(['page' => 1, 'itemsPerPage' => 1]);
$response = $controller->filterWithPaginate($request);
echo json_encode($response->getData(true), JSON_PRETTY_PRINT);
