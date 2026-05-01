<?php
use App\Models\Order;
use Carbon\Carbon;
use App\Services\Returns\ReturnsActionService;
use App\Services\Resources\ResourceService;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$searchTerm = 'V45532537';
$service = new ReturnsActionService(new ResourceService());
$query = $service->searchOrdersReturns($searchTerm, []);

echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n";
echo "Count: " . $query->count() . "\n";

foreach ($query->get() as $order) {
    echo "Found Order ID: " . $order->id . " Date: " . $order->order_date . "\n";
}
