<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SupplierConnection;
use App\Helpers\FtpCrypt;
use App\Services\Suppliers\SupplierConnectionService;
use Illuminate\Support\Facades\Log;

$token = 'GLyQBJZhVhEgCoGm1QTxKxgeUTR9PWPUEcR6GbWJEAVopyV8Tj9Av8JMuYIbmsF7ZcIZapDPUp3nCJnU';

try {
    $conn = SupplierConnection::where('supplier_id', 3)->first();
    if (!$conn) {
        die("Conexión no encontrada");
    }

    echo "Updating token...\n";
    $conn->password = FtpCrypt::encrypt($token);
    $conn->save();

    echo "Fetching data...\n";
    $service = app(SupplierConnectionService::class);
    
    $token = FtpCrypt::decrypt($conn->password);
    $client = (new \React\Http\Browser(new \React\Socket\Connector(['timeout' => 1800])))->withTimeout(1800.0);
    
    echo "Endpoint: https://apienterprise.cristmedicals.com/api/v1/articulos?co_cli=FAR00818\n";
    $rawResponse = $service->fetchFromAPI($token, [], $client, 'https://apienterprise.cristmedicals.com/api/v1/articulos?co_cli=FAR00818', 'get');
    
    echo "Raw Response (Articulos):\n";
    print_r($rawResponse);
    
    $data = $service->fetchData($conn);

    echo "Resultados:\n";
    echo "- Productos: " . count($data['products'] ?? []) . "\n";
    echo "- Facturas: " . count($data['invoices'] ?? []) . "\n";

    if (count($data['products'] ?? []) > 0) {
        echo "\nEjemplo de producto:\n";
        print_r(array_slice($data['products'], 0, 1));
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
