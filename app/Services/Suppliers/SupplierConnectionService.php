<?php

namespace App\Services\Suppliers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Helpers\FtpCrypt;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Socket\Connector;
use React\EventLoop\Loop;
use GuzzleHttp\Psr7\Request;

class SupplierConnectionService
{
    public function fetchData(SupplierConnection $connection)
    {
        switch ($connection->type) {
            case "ftp":
                return $this->fetchFromFtp($connection);
            case "sftp":
                return $this->fetchFromSftp($connection);
            case "http":
            case "api":
                return $this->fetchFromHttp($connection);
            default:
                throw new Exception("Tipo de conexión no soportado");
        }
    }

    public function fetchFromFtp(SupplierConnection $connection)
    {
        $host = $connection->host;
        $port = $connection->port ?? 21;
        $user = $connection->username;
        $pass = FtpCrypt::decrypt($connection->password);

        // Valida la conexión en texto plano
        $ftp = @ftp_connect($host, $port, 10);
        if ($ftp === false) {
            throw new Exception('No se pudo conectar al servidor FTP');
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            @ftp_close($ftp);

            // Si el inicio fallo usa ssl para intentar conectarse de nuevo
            $ftp = @ftp_ssl_connect($host, $port, 90);
            if ($ftp === false) {
                throw new Exception('No se pudo conectar al servidor FTP');
            }

            $login = ftp_login($ftp, $user, $pass);
            if ($login === false) {
                throw new Exception('Credenciales inválidas');
            }
        }

        ftp_pasv($ftp, $connection->pasv); // Modo pasivo

        // Productos
        $tempFile = tempnam(sys_get_temp_dir(), "ftp_");
        if (@ftp_get($ftp, $tempFile, $connection->path, FTP_BINARY)) {
            $content = file_get_contents($tempFile);
            $content_encoded = mb_convert_encoding($content, "UTF-8", "ISO-8859-1"); // Convierte a UTF-8 para devolver los resultados como JSON correctamente
            $productData = $this->parseDynamicContent($content_encoded, $connection);
        } else {
            throw new Exception("No se pudo guardar los productos");
        }

        // Facturas (si tiene ruta definida)
        $invoiceResults = [];
        $seenInvoiceNumbers = [];

        if (!empty($connection->invoice_path)) {
            $files = ftp_nlist($ftp, $connection->invoice_path);
            $filter = $connection->invoice_structure['filter'] ?? null;

            $startsWith = $filter['starts_with'] ?? '';
            $endsWith = $filter['ends_with'] ?? '.txt';
            $files = array_filter($files, function ($file) use ($startsWith, $endsWith) {
                $name = basename($file);
                return str_starts_with($name, $startsWith) && str_ends_with($name, $endsWith);
            });

            foreach ($files as $filePath) {
                if (!str_ends_with($filePath, ".txt")) {
                    continue;
                }
                $tempInvoice = tempnam(sys_get_temp_dir(), "inv_");

                if (@ftp_get($ftp, $tempInvoice, $filePath, FTP_BINARY)) {
                    $invoiceContent = file_get_contents($tempInvoice);
                    $parsed = $this->invoiceTxtParser($invoiceContent, $connection, $seenInvoiceNumbers);

                    if (!empty($parsed) && !empty($parsed['header'])) {
                        $invoiceResults[] = $parsed;
                    }
                }
            }
        }

        if ($ftp !== false) {
            @ftp_close($ftp);
        }

        return [
            "products" => $productData ?? [],
            "invoices" => $invoiceResults,
        ];
    }

    public function fetchFromSftp(SupplierConnection $connection)
    {
        return [];
    }

    public function fetchFromHttp(SupplierConnection $connection)
    {
        try {
            $connector = new Connector(['timeout' => 1800]);
            $client = (new Browser($connector))->withTimeout(1800.0);

            $loginResponse = Http::post($connection->host, [
                "usuario" => $connection->username,
                "clave" => FtpCrypt::decrypt($connection->password),
            ]);

            // Productos
            $payload = $this->buildPayload($connection, 'productos');

            $productResponse = $this->fetchFromAPI($loginResponse->json()["token"], $payload, $client, $connection->path);
            $productCsvString = $this->convertJsonArrayToCsvString($productResponse);

            $productData = $this->parseDynamicContent($productCsvString, $connection);

            // Facturas (si tiene ruta definida)
            if (!empty($connection->invoice_path)) {
                $seenInvoiceNumbers = [];
                $invoiceResults = [];


                $payloadInvoice = $this->buildPayload($connection, 'facturas');
                $invoiceResponse = $this->fetchFromAPI($loginResponse->json()["token"], $payloadInvoice, $client, $connection->invoice_path);

                foreach ($invoiceResponse as $invoice) {
                    $cod_invoice = $invoice['InvoiceCode'] ?? null;

                    if (!$cod_invoice || in_array($cod_invoice, $seenInvoiceNumbers)) {
                        continue;
                    }

                    $payloadInvoiceDetails = $this->buildPayload($connection, 'factura_detalle', $cod_invoice);
                    $invoiceDetailsResponse = $this->fetchFromAPI($loginResponse->json()["token"], [], $client, $payloadInvoiceDetails['url'], 'get');

                    $flatData = [];

                    foreach ($invoiceDetailsResponse as $detail) {
                        // Prefijar claves del encabezado
                        $prefixedHeader = [];
                        foreach ($invoice as $key => $value) {
                            $prefixedHeader["header_$key"] = $value;
                        }

                        // Prefijar claves del detalle
                        $prefixedDetail = [];
                        foreach ($detail as $key => $value) {
                            $prefixedDetail["detail_$key"] = $value;
                        }

                        // Combinar sin colisión
                        $flatRow = array_merge($prefixedHeader, $prefixedDetail);
                        $flatData[] = $flatRow;
                    }

                    $invoiceCsvString = $this->convertJsonArrayToCsvString($flatData);
                    $parsed = $this->invoiceTxtParser($invoiceCsvString, $connection, $seenInvoiceNumbers);

                    if (!empty($parsed) && !empty($parsed['header'])) {
                        $invoiceResults[] = $parsed;
                    }
                }
            }

            return [
                "products" => $productData ?? [],
                "invoices" => $invoiceResults ?? [],
            ];
        } catch (\Exception $e) {
            Log::alert("Supplier connection service");
            Log::error($e);
            throw new \Exception("No se pudo establecer la conexión");
        }
    }

    public function parseDynamicContent(string $content, SupplierConnection $connection)
    {
        $now = now();
        $supplierId = $connection->supplier_id;
        $structure = $connection->structure;
        $has_header = $connection->has_header;

        $lines = array_filter(explode("\n", trim($content)), "trim");

        $barcodes = [];

        // ignora la primera fila si contiene encabezados en vez de registros
        if ($has_header) {
            array_shift($lines);
        }

        $usdCurrency = ExchangeRate::where("currency_code", "USD")
            ->whereDate("created_at", \Carbon\Carbon::today())
            ->first();

        if (!isset($usdCurrency)) {
            $exitCode = Artisan::call("app:update-exchange-rate");

            if ($exitCode === 0) {
                $usdCurrency = ExchangeRate::where("currency_code", "USD")
                    ->whereDate("created_at", \Carbon\Carbon::today())
                    ->first();
            } else {
                Log::error("Failed to fetch exchange rate");
                throw new \Exception("No se pudo guardar la tasa del día USD");
            }
        }

        $barcodeKey = collect($structure)->search(fn($f) => ($f["target"] ?? null) === "barcode_match");

        foreach ($lines as $line) {
            if (!empty($structure_for_parsing)) {
                $line = $this->parseFixedWidth($line, $structure_for_parsing);
            }
            $cols = explode(';', $line);
            $barcodes[] = trim($cols[$barcodeKey] ?? "");
        }

        $structure_for_parsing = json_decode($connection->parse_using);
        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with("laboratory")->whereIn("barcode", $barcodes)->get()->keyBy("barcode");

        $result = collect($lines)->map(function (string $line) use ($structure, $now, $usdCurrency, $supplierId, $products, $structure_for_parsing) {
            if (!empty($structure_for_parsing)) {
                $line = $this->parseFixedWidth($line, $structure_for_parsing);
            }
            $cols = explode(";", $line);
            $entry = [
                "supplier_id" => $supplierId,
                "created_at" => $now,
                "updated_at" => $now,
                "connection_date" => $now,
                "laboratory" => null,
                "product_id" => null,
                "unit_cost_with_discount" => null,
                "unit_cost_usd_with_discount" => null,
            ];

            $hasUnitCostUsd = in_array("unit_cost_usd", array_column($structure, "target"), true);
            $table_structure = collect($structure)->filter(fn($f) => $f["target"] ?? null);
            $missingBarcode = false;

            //$quantity = 0;
            foreach ($table_structure as $index => $meta) {
                $raw = $cols[$index] ?? "";
                $value = trim($raw);

                switch ($meta["type"]) {
                    case "string":
                        $entry[$meta["target"]] = $value;
                        break;

                    case "integer":
                        $entry[$meta["target"]] = $value;
                        break;

                    case "decimal":
                        if (is_numeric($value)) {
                            $newValue = number_format((float) $value, 2, ".", "");

                            if (in_array($meta["target"], ["exisMerida", "exisCaracas", "exisOriente", "quantity"])) {
                                $entry["quantity"] = $value;
                                //$quantity += $newValue;
                                break;
                            }

                            if ($meta["target"] === "unit_cost_usd") {
                                $entry["unit_cost_usd"] = $newValue;
                                break;
                            }

                            // Si ya tiene el precio en bs y usd
                            if ($hasUnitCostUsd) {
                                $entry[$meta["target"]] = $newValue;
                                break;
                            } else {
                                // Precio en bs calcula con la tasa  usd del dia
                                if (isset($meta["currency"]) && $meta["currency"] === "usd") {
                                    $entry[$meta["target"]] = number_format(
                                        (float) ($newValue * $usdCurrency->rate),
                                        2,
                                        ".",
                                        "",
                                    );
                                    $entry["unit_cost_usd"] = $newValue;

                                    break;
                                } else {
                                    // Obtiene el equivalente de bs en usd
                                    $entry[$meta["target"]] = $newValue;
                                    $entry["unit_cost_usd"] = number_format(
                                        (float) ($newValue / $usdCurrency->rate),
                                        2,
                                        ".",
                                        "",
                                    );

                                    break;
                                }
                            }
                        } else {
                            $entry[$meta["target"]] = null;
                        }
                        break;

                    case "date":
                        if ($value === "0000-00-00" || $value === "-0001-11-30" || strtoupper($value) === "NULL" || trim($value) === "") {
                            $entry[$meta["target"]] = null;
                            break;
                        }

                        $dt = \DateTime::createFromFormat("Y-m-d", $value);
                        if ($dt && $dt->format("Y-m-d") === $value) {
                            $entry[$meta["target"]] = $dt->format("Y-m-d");
                            break;
                        }

                        $dt = \DateTime::createFromFormat("d/m/Y", $value);
                        if ($dt && $dt->format("d/m/Y") === $value) {
                            $entry[$meta["target"]] = $dt->format("Y-m-d");
                            break;
                        }

                        $dt = \DateTime::createFromFormat("Y-m-d", "{$value}-01");
                        if ($dt && $dt->format("Y-m") === $value) {
                            $entry[$meta["target"]] = $dt->format("Y-m-d");
                            break;
                        }

                        $entry[$meta["target"]] = null;
                        break;
                }

                if ($meta["target"] === "barcode_match" && $value !== "") {
                    $product = $products->get($value);

                    if ($product) {
                        $entry["laboratory"] = $product?->laboratory?->name;
                        $entry["product_id"] = $product?->id;
                    } else {
                        $missingBarcode = true; // lo marcamos para crear luego
                    }
                }
            }

            if (isset($entry["unit_cost_usd"]) && is_numeric($entry["unit_cost_usd"])) {
                $entry["unit_cost"] = number_format(
                    (float) ($entry["unit_cost_usd"] * $usdCurrency->rate),
                    2,
                    ".",
                    ""
                );
            }

            //$entry["quantity"] = $quantity;

            // if ($missingBarcode && !Product::where('barcode', $entry['barcode_match'])->exists()) {
            //     $stock = 0;

            //     if ($entry['supplier_id'] == 2) {
            //         foreach (['exisMerida', 'exisCaracas', 'exisOriente'] as $campo) {
            //             $stock += intval($entry[$campo] ?? 0);
            //         }
            //     } else
            //         $stock = $entry['quantity'];

            //     $newProduct = Product::create([
            //         'barcode' => $entry['barcode_match'],
            //         'name' => $entry['name'] ?? 'Producto sin nombre',
            //         'unit_cost' => $entry['unit_cost'] ?? 0,
            //         'sale_price' => $entry['unit_cost'] ?? 0,
            //         'stock' => $stock,
            //         'active_ingredient' => $entry['active_ingredient'] ?? 'Producto FTP',
            //         'sales_average' => $entry['sales_average'] ?? 0
            //     ]);

            //     $entry['product_id'] = $newProduct->id;

            //     $products->put($missingBarcode, $newProduct); // actualiza el cache local
            // }

            return $entry;
        });

        return $result->toArray();
    }

    public function invoiceTxtParser(string $content, SupplierConnection $connection, array &$seenInvoiceNumbers = []): array
    {
        $lines = array_filter(explode("\n", trim($content)), "trim");
        $structure = $connection->invoice_structure;
        $separator = $structure["separator"] ?? ";";

        $invoices = [];
        $bufferLines = [];

        $barcodeField = collect($structure["lines"])->pluck("field")->search("barcode");
        $barcodes = [];

        foreach ($lines as $line) {
            $cols = explode($separator, $line);
            $tipo = trim($cols[0] ?? "");

            if ($tipo === "R" && $barcodeField !== false) {
                $barcode = trim($cols[$barcodeField] ?? "");
                if ($barcode !== "") {
                    $barcodes[] = $barcode;
                }
            }
        }

        $products = Product::whereIn("barcode", array_unique($barcodes))->get()->keyBy("barcode");

        $mode = $structure['mode'] ?? 'grouped';

        if ($mode === 'flat') {
            $invoiceGroups = [];

            foreach ($lines as $line) {
                $cols = explode($separator, $line);

                // Encabezado desde la misma línea
                $header = [];
                foreach ($structure['header'] as $index => $meta) {
                    $raw = $cols[$index] ?? '';
                    $header[$meta['field']] = $this->castValue($raw, $meta);
                }

                $invoiceNumber = $header['invoice_number'] ?? null;
                if (!$invoiceNumber || in_array($invoiceNumber, $seenInvoiceNumbers))
                    continue;

                // Línea de producto
                $lineData = [];
                foreach ($structure['lines'] as $index => $meta) {
                    $raw = $cols[$index] ?? '';
                    $lineData[$meta['field']] = $this->castValue($raw, $meta);
                }

                $barcode = $lineData['barcode'] ?? null;

                // ✅ Buscar o crear producto
                if ($barcode) {
                    $product = $products->get($barcode);
                    if (!$product) {
                        // Crear producto si no existe
                        $product = $this->createProductFromInvoice($lineData, $connection->supplier_id);
                        if ($product) {
                            $products->put($barcode, $product);
                        }
                    }
                    $lineData['product_id'] = $product?->id;
                }

                // Agrupar por número de factura
                if (!isset($invoiceGroups[$invoiceNumber])) {
                    $invoiceGroups[$invoiceNumber] = [
                        'header' => $header,
                        'lines' => [],
                    ];
                }

                $invoiceGroups[$invoiceNumber]['lines'][] = $lineData;
            }

            foreach ($invoiceGroups as $number => $invoice) {
                $invoices = $invoice;
                $seenInvoiceNumbers[] = $number;
            }
        } else {
            // ✅ Variable para guardar el exchange_rate del header actual
            $currentExchangeRate = null;

            foreach ($lines as $line) {
                $cols = explode($separator, $line);
                $tipo = trim($cols[0] ?? "");

                if ($tipo === "E") {
                    $header = [];

                    foreach ($structure["header"] as $index => $meta) {
                        $raw = $cols[$index] ?? "";
                        $value = $this->castValue($raw, $meta);
                        $header[$meta["field"]] = $value;
                    }

                    $invoiceNumber = $header['invoice_number'] ?? null;
                    if ($invoiceNumber && in_array($invoiceNumber, $seenInvoiceNumbers)) {
                        $bufferLines = [];
                        continue;
                    }

                    if (in_array($connection->supplier_id, [15, 38])) {
                        $totalUSD = floatval($header["total_usd"] ?? 0);
                        $exchangeRate = floatval($header["exchange_rate"] ?? 0);
                        $currentExchangeRate = $exchangeRate; // ✅ Guardar para las líneas

                        $header["total_amount"] = $totalUSD * $exchangeRate;

                        if (isset($header["tax_amount"])) {
                            $header["taxable_base"] = (floatval($header["tax_amount"]) * 100) / 16; // Suponiendo 16% de IVA
                            $header["exempt_amount"] = floatval($header["total_amount"]) - floatval($header["tax_amount"]) - floatval($header["taxable_base"]);
                        } else {
                            $header["exempt_amount"] = $header["total_amount"];
                        }

                        $header["status_payment"] = 0;
                    }

                    $invoices = [
                        "header" => $header,
                        "lines" => $bufferLines,
                    ];

                    $seenInvoiceNumbers[] = $invoiceNumber;
                    $bufferLines = [];
                }

                if ($tipo === "R") {
                    $lineData = [];

                    foreach ($structure["lines"] as $index => $meta) {
                        $raw = $cols[$index] ?? "";
                        $value = $this->castValue($raw, $meta);
                        $lineData[$meta["field"]] = $value;
                    }

                    $barcode = $lineData["barcode"] ?? null;

                    // ✅ Solo crear producto si no existe
                    if ($barcode) {
                        $product = $products->get($barcode);
                        if (!$product) {
                            $product = $this->createProductFromInvoice($lineData, $connection->supplier_id);
                            if ($product) {
                                $products->put($barcode, $product);
                            }
                        }
                        $lineData['product_id'] = $product?->id;
                    }

                    $unitCost = floatval($lineData["unit_cost"] ?? 0);
                    $quantity = intval($lineData["quantity"] ?? 0);
                    $lineData["total_cost"] = $unitCost * $quantity;

                    $bufferLines[] = $lineData;
                }
            }
        }

        return $invoices;
    }

    private function createProductFromInvoice(array $lineData, int $supplierId): ?Product
    {
        $barcode = $lineData['barcode'] ?? null;

        if (!$barcode) {
            return null;
        }

        // Verificar si ya existe
        if (Product::where('barcode', $barcode)->exists()) {
            return Product::where('barcode', $barcode)->first();
        }

        try {
            $productName = $lineData['descripcion_producto'] ?? 'Producto desde factura';

            $newProduct = Product::create([
                'barcode' => $barcode,
                'name' => $productName,
                'active_ingredient' => 'N/A',
                'laboratory_id' => null,
                'origin_id' => null,
                'category_id' => null,
                'group_id' => null,
                'unit_cost' => floatval($lineData['unit_cost'] ?? 0),
                'sale_price' => floatval($lineData['unit_cost'] ?? 0),
                'iva' => 0,
                'is_colombian_origin' => false,
                'psychotropic' => false,
                'stock' => 0,
                'sales_average' => 0,
                'is_deleted' => false,
            ]);

            Log::info('✅ Producto creado desde factura', [
                'supplier_id' => $supplierId,
                'barcode' => $barcode,
                'product_id' => $newProduct->id,
                'name' => $newProduct->name
            ]);

            return $newProduct;
        } catch (\Exception $e) {
            Log::error('❌ Error al crear producto desde factura', [
                'supplier_id' => $supplierId,
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function castValue(string $raw, array $meta): mixed
    {
        $value = trim($raw);

        return match ($meta["type"]) {
            "string" => $value,
            "integer" => is_numeric($value) ? (int) $value : null,
            "decimal" => is_numeric($value) ? number_format((float) $value, 2, ".", "") : null,
            "date" => $this->parseDate($value, $meta["format"] ?? null),
            default => $value,
        };
    }

    private function parseDate(string $value, ?string $preferredFormat = null): ?string
    {
        if ($value === "" || $value === "0000-00-00" || strtoupper($value) === "NULL") {
            return null;
        }

        $formats = array_filter([
            $preferredFormat,
            "Y-m-d",
            "d/m/Y",
            "d-m-Y",
            "m/d/Y",
            "Ymd",
            "Y-m-d\TH:i:s",
            "Y-m-d\TH:i:s.u\Z",
            "Y-m-d\TH:i:sP",
        ]);

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format("Y-m-d");
            }
        }

        return null;
    }

    public function fetchFromAPI($token, $data, $client, $path, $method = 'post'): array
    {
        $productResponse = [];

        $client->{$method}(
            $path,
            [
                'autorizacion' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            $method === 'post' ? json_encode($data) : null
        )->then(function (ResponseInterface $response) use (&$productResponse) {
            $productResponse = json_decode((string) $response->getBody(), true);
        }, function (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        });

        Loop::run();

        return $productResponse;
    }

    public function buildPayload(SupplierConnection $connection, string $endpoint, $extra = null): array
    {
        $supplierId = $connection->supplier_id;
        //$config = config("suppliers.{$supplierId}");
        $config = require app_path("SupplierConfigs/{$supplierId}.php");

        if (!isset($config[$endpoint])) {
            throw new \Exception("No se encontró configuración para {$endpoint} en proveedor {$supplierId}");
        }

        $payload = $config[$endpoint];

        if (is_callable($payload)) {
            return $payload($connection, $extra);
        }

        return $payload;
    }

    public function convertJsonArrayToCsvString(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $csv = fopen('php://temp', 'r+');

        // Escribir encabezados
        fputcsv($csv, array_keys($data[0]), ';');

        // Escribir filas
        foreach ($data as $row) {
            fputcsv($csv, array_map(function ($value) {
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }, $row), ';');
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return $csvContent;
    }

    public function parseFixedWidth(string $line, array $map, string $encoding = 'UTF-8'): string
    {
        $offset = 0;
        $out = [];

        foreach ($map as $field) {
            $width = (int) $field->width;
            $slice = mb_substr($line, $offset, $width, $encoding);
            $out[] = trim($slice);
            $offset += $width;
        }

        return implode(';', $out);
    }
}
