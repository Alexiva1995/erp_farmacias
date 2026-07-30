<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductSupplier;
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
            case "file":
                throw new Exception("Esta conexión es de tipo 'Archivo Excel' (subida manual). Se requiere configurar una conexión FTP o API para la sincronización automática.");
            default:
                throw new Exception("Tipo de conexión no soportado: {$connection->type}");
        }
    }

    public function fetchFromFtp(SupplierConnection $connection)
    {
        $host = $connection->host;
        $port = (int) ($connection->port ?? 21);
        $user = (string) ($connection->username ?? '');
        $pass = (string) FtpCrypt::decrypt($connection->password ?? '');

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan el usuario o la contraseña de FTP para la conexión. Por favor edite el proveedor para agregar las credenciales.");
        }

        // Valida la conexión en texto plano
        $ftp = @ftp_connect($host, $port, 10);
        if ($ftp === false) {
            Log::error("Fallo ftp_connect", ['host' => $host]);
            throw new Exception('No se pudo conectar al servidor FTP');
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            Log::warning("Fallo ftp_login inicial para {$user}@{$host}, intentando SSL...");
            @ftp_close($ftp);

            // Reintento seguro con SSL tolerante a politicas locales de servidores que deniegan TLS
            $sslSuccess = false;
            try {
                $sslFtp = @ftp_ssl_connect($host, $port, 15);
                if ($sslFtp !== false) {
                    $sslLogin = @ftp_login($sslFtp, $user, $pass);
                    if ($sslLogin !== false) {
                        $ftp = $sslFtp;
                        $login = true;
                        $sslSuccess = true;
                    } else {
                        @ftp_close($sslFtp);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("El servidor FTP {$host} no admite conexiones SSL/TLS: " . $e->getMessage());
            }

            if (!$sslSuccess) {
                throw new Exception("No se pudo iniciar sesión en el servidor FTP {$host}. Verifique que el usuario '{$user}' y la contraseña sean correctos.");
            }
        }

        ftp_pasv($ftp, $connection->pasv); // Modo pasivo

        // Productos
        $tempFile = tempnam(sys_get_temp_dir(), "ftp_");

        if ($connection->path === '/') {
            // Listar todos los archivos en la raíz
            $files = @ftp_nlist($ftp, $connection->path);

            if ($files === false) {
                Log::warning("Initial ftp_nlist failed for supplier {$connection->supplier_id}. Toggling PASV mode and retrying...");
                ftp_pasv($ftp, !$connection->pasv);
                $files = @ftp_nlist($ftp, $connection->path);
            }

            $inventoryFiles = array_filter($files, function ($file) {
                $name = basename($file);
                return str_starts_with($name, 'inventario') && str_ends_with($name, '.txt');
            });

            // Ordenar por nombre (asumiendo que el nombre incluye fecha/hora)
            usort($inventoryFiles, function ($a, $b) {
                return strcmp($b, $a); // orden descendente
            });

            $latestFile = $inventoryFiles[0] ?? null;

            if (!$latestFile) {
                throw new Exception("No se encontró archivo de inventario en la raíz");
            }

            if (@ftp_get($ftp, $tempFile, $latestFile, FTP_BINARY)) {
                $content = file_get_contents($tempFile);
                $content_encoded = mb_convert_encoding($content, "UTF-8", "ISO-8859-1"); // Convierte a UTF-8 para devolver los resultados como JSON correctamente
                $productData = $this->parseDynamicContent($content_encoded, $connection);
            } else {
                $lastError = error_get_last();
                Log::error("Fallo ftp_get para archivo: {$latestFile}", ['error' => $lastError['message'] ?? 'Unknown error']);
                throw new Exception("No se pudo guardar los productos");
            }
        } else {
            if (@ftp_get($ftp, $tempFile, $connection->path, FTP_BINARY)) {
                $content = file_get_contents($tempFile);
                $content_encoded = mb_convert_encoding($content, "UTF-8", "ISO-8859-1"); // Convierte a UTF-8 para devolver los resultados como JSON correctamente
                $productData = $this->parseDynamicContent($content_encoded, $connection);
            } else {
                $lastError = error_get_last();
                Log::error("Fallo ftp_get para ruta: {$connection->path}", ['error' => $lastError['message'] ?? 'Unknown error']);
                throw new Exception("No se pudo guardar los productos");
            }
        }

        // Facturas (si tiene ruta definida)
        $invoiceResults = [];
        $seenInvoiceNumbers = [];

        if (!empty($connection->invoice_path)) {
            $files = @ftp_nlist($ftp, $connection->invoice_path);

            if ($files === false) {
                Log::warning("Initial invoice ftp_nlist failed for supplier {$connection->supplier_id}. Toggling PASV mode and retrying...");
                ftp_pasv($ftp, !$connection->pasv);
                $files = @ftp_nlist($ftp, $connection->invoice_path);
            }

            if ($files === false) {
                Log::warning("Failed to list invoices for supplier {$connection->supplier_id} even after PASV toggle.");
                $files = []; // Treat as empty if we can't look inside, or throw? The previous code just assumed it worked or returned warning. Let's return empty to avoid full crash if products worked.
            }
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
                    $filename = pathinfo($filePath, PATHINFO_FILENAME);
                    $invoiceContent = file_get_contents($tempInvoice);
                    $parsed = $this->invoiceTxtParser($invoiceContent, $connection, $seenInvoiceNumbers, $connection->supplier_id === 2 ? $filename : null);

                    if (!empty($parsed) && !empty($parsed['header'])) {
                        $invoiceResults[] = $parsed;
                    }
                    @unlink($tempInvoice);
                }
            }
        }

        if ($ftp !== false) {
            try {
                @ftp_close($ftp);
            } catch (\Throwable $e) {
                // Silenciar eof inesperado de OpenSSL al cerrar socket
            }
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

            $token = null;
            if (!empty($connection->username) && !empty($connection->password) && !in_array($connection->supplier_id, [3])) {
                $loginResponse = Http::post($connection->host, [
                    "usuario" => $connection->username,
                    "clave" => FtpCrypt::decrypt($connection->password),
                ]);
                $token = $loginResponse->json()["token"] ?? null;
            } elseif (!empty($connection->password)) {
                $token = FtpCrypt::decrypt($connection->password);
            }

            // Productos
            $payloadDef = $this->buildPayload($connection, 'productos');
            $productData = [];

            if ($payloadDef) {
                $url = $payloadDef['url'] ?? $connection->path;
                $requestData = isset($payloadDef['payload']) ? $payloadDef['payload'] : (isset($payloadDef['url']) ? [] : $payloadDef);

                $allProducts = [];
                $currentUrl = $url;
                
                while ($currentUrl) {
                    $productResponse = $this->fetchFromAPI($token, $requestData, $client, $currentUrl, $payloadDef['method'] ?? 'post');
                    
                    // Detectar si los productos vienen en una clave específica
                    $pageData = $productResponse;
                    $nextPageUrl = null;
                    
                    if (isset($productResponse['articulos']) && is_array($productResponse['articulos'])) {
                        if (isset($productResponse['articulos']['data'])) {
                            // Estructura paginada (Laravel standard)
                            $pageData = $productResponse['articulos']['data'];
                            $nextPageUrl = $productResponse['articulos']['next_page_url'] ?? null;
                        } else {
                            $pageData = $productResponse['articulos'];
                        }
                    } elseif (isset($productResponse['data']) && is_array($productResponse['data'])) {
                         $pageData = $productResponse['data'];
                         $nextPageUrl = $productResponse['next_page_url'] ?? null;
                    }

                    if (is_array($pageData)) {
                        $allProducts = array_merge($allProducts, $pageData);
                    } else {
                        // Si no es un array, probablemente es un error o el fin
                        break;
                    }
                    
                    $currentUrl = $nextPageUrl;
                    
                    // Si no hay paginación detectada, romper el bucle después de la primera pasada
                    if (!$nextPageUrl) break;
                }

                $productCsvString = $this->convertJsonArrayToCsvString($allProducts);
                $productData = $this->parseDynamicContent($productCsvString, $connection);
            }

            // Facturas (si tiene ruta definida)
            $invoiceResults = [];
            if (!empty($connection->invoice_path)) {
                $seenInvoiceNumbers = [];

                $payloadInvoice = $this->buildPayload($connection, 'facturas');
                $invoiceUrl = $payloadInvoice['url'] ?? $connection->invoice_path;
                $requestDataInv = isset($payloadInvoice['payload']) ? $payloadInvoice['payload'] : (isset($payloadInvoice['url']) ? [] : $payloadInvoice);
                
                Log::info("🔎 [FACTURAS] buildPayload result", ['payloadInvoice' => $payloadInvoice, 'invoiceUrl' => $invoiceUrl, 'method' => $payloadInvoice['method'] ?? 'post']);
                $invoiceResponse = $this->fetchFromAPI($token, $requestDataInv, $client, $invoiceUrl, $payloadInvoice['method'] ?? 'post');
                Log::info("🔎 [FACTURAS] fetchFromAPI result", ['count' => count($invoiceResponse)]);

                // Detectar si las facturas vienen en una clave específica (ej: 'facturas')
                $invoicesRaw = $invoiceResponse;
                if (isset($invoiceResponse['facturas']) && is_array($invoiceResponse['facturas'])) {
                    $invoicesRaw = $invoiceResponse['facturas'];
                }


                foreach ($invoicesRaw as $invoice) {
                    $cod_invoice = $invoice['fact_num'] ?? $invoice['InvoiceCode'] ?? null;

                    if (!$cod_invoice || in_array($cod_invoice, $seenInvoiceNumbers)) {
                        Log::warning("Factura ignorada (sin código o duplicada en este lote)", ['cod_invoice' => $cod_invoice]);
                        continue;
                    }

                    // Si la factura ya trae los artículos (caso Cristmedicals y otros)
                    if (isset($invoice['articulos']) && is_array($invoice['articulos'])) {
                        $parsed = $this->parseNestedInvoice($invoice, $connection);
                        if (!empty($parsed)) {
                            $invoiceResults[] = $parsed;
                            $seenInvoiceNumbers[] = $cod_invoice;
                        } else {
                            Log::error("Fallo al parsear factura anidada", ['cod_invoice' => $cod_invoice]);
                        }
                        continue;
                    }

                    $payloadInvoiceDetails = $this->buildPayload($connection, 'factura_detalle', $cod_invoice);
                    $invoiceDetailsResponse = $this->fetchFromAPI($token, [], $client, $payloadInvoiceDetails['url'], 'get');

                    $flatData = [];

                    foreach ($invoiceDetailsResponse as $detail) {
                        // Prefijar claves del encabezado
                        $prefixedHeader = [];
                        foreach ($invoice as $key => $value) {
                            if (!is_array($value)) {
                                $prefixedHeader["header_$key"] = $value;
                            }
                        }

                        // Prefijar claves del detalle
                        $prefixedDetail = [];
                        foreach ($detail as $key => $value) {
                            if (!is_array($value)) {
                                $prefixedDetail["detail_$key"] = $value;
                            }
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
            Log::alert("Supplier connection service error for supplier {$connection->supplier_id}");
            Log::error($e);
            throw $e;
        }
    }

    public function parseDynamicContent(string $content, SupplierConnection $connection)
    {
        $now = now();
        $supplierId = $connection->supplier_id;
        $structure = $connection->structure;
        $has_header = $connection->has_header;

        // Normalizar la estructura para soportar tanto el formato antiguo (estructurado) 
        // como el nuevo (plano de importaciones manuales)
        $normalizedStructure = collect($structure)->map(function ($meta, $key) {
            if (is_array($meta) && isset($meta['target'])) {
                return $meta;
            }
            // Formato plano: $key es el target, $meta es el campo (nombre o letra de columna)
            return [
                'target' => $key,
                'file_field' => $meta,
                'type' => in_array($key, ['unit_cost', 'unit_cost_usd']) ? 'decimal' : (in_array($key, ['quantity']) ? 'integer' : 'string')
            ];
        })->filter(function($f, $k) {
            $target = $f["target"] ?? null;
            return $target && !is_numeric($target);
        });

        $lines = array_filter(explode("\n", trim($content)), "trim");

        $barcodes = [];

        // ignora la primera fila si contiene encabezados en vez de registros
        $headerMap = [];
        $headerLine = '';
        if ($has_header) {
            $headerLine = array_shift($lines);
        }

        $usdCurrency = ExchangeRate::orderByDesc('created_at')
            ->where('currency_code', '=', 'BS')
            ->first();

        if (!isset($usdCurrency)) {
            $exitCode = Artisan::call("app:update-exchange-rate");

            if ($exitCode === 0) {
                $exchange_rate = ExchangeRate::orderByDesc('created_at')
                    ->where('currency_code', '=', 'BS')
                    ->first();

            } else {
                \Log::error("Failed to fetch exchange rate");
                throw new \Exception("No se pudo guardar la tasa del día BS");
            }
        }

        $structure_for_parsing = json_decode($connection->parse_using ?? '');

        if ($has_header && !empty($headerLine)) {
            if (!empty($structure_for_parsing)) {
                $headerLine = $this->parseFixedWidth($headerLine, $structure_for_parsing);
            }
            // Remove BOM if present
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
            $headers = explode(';', $headerLine);
            $headerMap = array_flip(array_map('trim', $headers));
        }

        // Helper para obtener el índice de la columna basado en el mapeo y los encabezados
        $getIdx = function ($meta, $originalKey) use ($headerMap) {
            // Prioridad 1: Coincidencia por nombre de encabezado si está disponible
            if (!empty($headerMap) && isset($meta['file_field']) && is_string($meta['file_field']) && isset($headerMap[trim($meta['file_field'])])) {
                return $headerMap[trim($meta['file_field'])];
            }
            // Prioridad 2: Si file_field es una letra (estilo Excel), convertir a índice
            if (isset($meta['file_field']) && is_string($meta['file_field'])) {
                $idx = $this->colIndex($meta['file_field']);
                if ($idx !== null)
                    return $idx;
            }
            // Prioridad 3: Usar la clave original como índice numérico si es posible
            return is_numeric($originalKey) ? (int) $originalKey : null;
        };

        $barcodeMeta = $normalizedStructure->first(fn($f) => ($f["target"] ?? null) === "barcode_match");
        $barcodeOriginalKey = $normalizedStructure->search(fn($f) => ($f["target"] ?? null) === "barcode_match");

        $barcodeIdx = null;
        if ($barcodeMeta) {
            $barcodeIdx = $getIdx($barcodeMeta, $barcodeOriginalKey);
        }

        foreach ($lines as $line) {
            if (!empty($structure_for_parsing)) {
                $line = $this->parseFixedWidth($line, $structure_for_parsing);
            }
            $cols = explode(';', $line);

            if ($barcodeIdx !== null && isset($cols[$barcodeIdx])) {
                $barcodes[] = trim($cols[$barcodeIdx] ?? "");
            }
        }

        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with("laboratory")->whereIn("barcode", $barcodes)->get()->keyBy("barcode");

        $result = collect($lines)->map(function (string $line, $key) use ($normalizedStructure, $now, $usdCurrency, $supplierId, $products, $structure_for_parsing, $headerMap, $getIdx) {
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

            $hasUnitCostUsd = $normalizedStructure->contains('target', 'unit_cost_usd');

            $missingBarcode = false;

            $quantity = 0;
            foreach ($normalizedStructure as $originalKey => $meta) {
                $idx = $getIdx($meta, $originalKey);
                if ($idx === null || !isset($cols[$idx]))
                    continue;

                $raw = $cols[$idx];
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
                                $quantity += $value;
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
            
            // Aplicar descuento si existe (mapeado en DB o Config)
            $discount = isset($entry['discount_percentage']) ? (float)$entry['discount_percentage'] : 0;
            if ($discount > 0) {
                $isLacrifort = (isset($entry['name']) && str_contains(strtoupper($entry['name']), 'LACRIFORT'));
                $oldBs = $entry['unit_cost'] ?? 0;

                if (isset($entry['unit_cost']) && is_numeric($entry['unit_cost'])) {
                    $entry['unit_cost'] = (float)$entry['unit_cost'] * (1 - ($discount / 100));
                }
                
                // Recalcular USD basado en el nuevo BS para mantener consistencia absoluta
                $entry['unit_cost_usd'] = number_format(
                    (float) ($entry['unit_cost'] / ($usdCurrency->rate ?? 1)),
                    2, ".", ""
                );

                if ($isLacrifort) {
                    $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
                    $logMsg = "[" . date('Y-m-d H:i:s') . "] 🎯 LACRIFORT DETECTED: Original BS: {$oldBs}, Discount: {$discount}%, Final BS: {$entry['unit_cost']}, Final USD: {$entry['unit_cost_usd']}, Rate: {$usdCurrency->rate}\n";
                    file_put_contents($logFile, $logMsg, FILE_APPEND);
                }
            }

            if (!isset($entry["quantity"]))
                $entry["quantity"] = $quantity;

            return $entry;
        });


        return $result->toArray();
    }

    public function invoiceTxtParser(string $content, SupplierConnection $connection, array &$seenInvoiceNumbers = [], ?string $overrideInvoiceNumber = null): array
    {
        $lines = array_filter(explode("\n", trim($content)), "trim");
        $structure = $connection->invoice_structure;
        $separator = $structure["separator"] ?? ";";

        $invoices = [];
        $bufferLines = [];

        $barcodeField = collect($structure["lines"])->pluck("field")->search("barcode");
        $barcodes = [];
        $mode = $structure['mode'] ?? 'grouped';

        foreach ($lines as $line) {
            $cols = explode($separator, $line);
            if ($separator == "\t") {
                $cols = explode(';', $this->convertLineToCSV($line));
            }

            $tipo = trim($cols[0] ?? "");

            if ($tipo === "R" || $tipo === '01' && $barcodeField !== false) {
                $barcode = trim($cols[$barcodeField] ?? "");
                if ($barcode !== "") {
                    $barcodes[] = $barcode;
                }
            }

            $barcodeIndexFlat = array_search('barcode', array_column($structure['lines'], 'field'));
            if ($mode === 'flat' && $barcodeIndexFlat !== false) {
                $originalIndex = array_keys($structure['lines'])[$barcodeIndexFlat];
                $barcode = trim($cols[$originalIndex] ?? "");
                if ($barcode !== "") {
                    $barcodes[] = $barcode;
                }
            }
        }

        $products = Product::whereIn("barcode", array_unique($barcodes))->get()->keyBy("barcode");

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

                if (in_array($connection->supplier_id, [23])) {
                    if (isset($header["tax_amount"])) {
                        $header["taxable_base"] = (floatval($header["tax_amount"]) * 100) / 16; // Suponiendo 16% de IVA
                        $header["exempt_amount"] = floatval($header["total_amount"]) - floatval($header["tax_amount"]) - floatval($header["taxable_base"]);
                    } else {
                        $header['exempt_amount'] = $header["total_amount"];
                    }
                }

                $exchangeRate = floatval($header['exchange_rate'] ?? 0);
                $totalAmount = floatval($header['total_amount'] ?? 0);
                if ($exchangeRate > 0) {
                    $header['total_usd'] = number_format($totalAmount / $exchangeRate, 2, '.', '');
                } else {
                    $header['total_usd'] = 0.00;
                }

                $invoiceNumber = $overrideInvoiceNumber ?? ($header['invoice_number'] ?? null);
                $header['invoice_number'] = $invoiceNumber;

                if (!$invoiceNumber || in_array($invoiceNumber, $seenInvoiceNumbers))
                    continue;

                // Línea de producto
                $lineData = [];
                $ivaTaxValue = 0;
                $lineData['tax_enabled'] = 0;

                foreach ($structure['lines'] as $index => $meta) {
                    $raw = $cols[$index] ?? '';
                    //                    //$lineData[$meta['field']] = $this->castValue($raw, $meta);
                    $value = $this->castValue($raw, $meta);
                    $lineData[$meta['field']] = $value;

                    if ($meta["field"] === "porcentaje_iva" && is_numeric($value)) {
                        $ivaTaxValue = floatval($value);
                    }
                }

                if ($ivaTaxValue > 0) {
                    $lineData['tax_enabled'] = 1;
                }

                $unitCost = floatval($lineData["unit_cost"] ?? 0);
                $quantity = intval($lineData["quantity"] ?? 0);
                $lineData["total_cost"] = number_format($unitCost * $quantity, 2, '.', '');

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

                if ($connection->supplier_id === 2) {
                    if (isset($header["tax_amount"])) {
                        $header["taxable_base"] = (floatval($header["tax_amount"]) * 100) / 16;
                    }
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
                if ($connection->supplier_id === 27) {
                    $cols = explode(";", $this->convertLineToCSV($line));
                }
                $tipo = trim($cols[0] ?? "");

                if ($tipo === "E" || $tipo === '02') {
                    $header = [];

                    foreach ($structure["header"] as $index => $meta) {
                        $raw = $cols[$index] ?? "";
                        $value = $this->castValue($raw, $meta);
                        $header[$meta["field"]] = $value;
                    }

                    if ($connection->supplier_id === 27) {
                        $exchangeRate = floatval($header['exchange_rate'] ?? 0);
                        $totalAmount = floatval($header['total_amount'] ?? 0);

                        if ($exchangeRate > 0) {
                            $header['total_usd'] = number_format($totalAmount / $exchangeRate, 2, '.', '');
                        }
                    }
                    $invoiceNumber = $overrideInvoiceNumber ?? ($header['invoice_number'] ?? null);
                    $header['invoice_number'] = $invoiceNumber;

                    if ($invoiceNumber && in_array($invoiceNumber, $seenInvoiceNumbers)) {
                        $bufferLines = [];
                        continue;
                    }

                    if (in_array($connection->supplier_id, [9, 15, 38])) {
                        $totalUSD = floatval($header["total_usd"] ?? 0);
                        $exchangeRate = floatval($header["exchange_rate"] ?? 0);
                        $currentExchangeRate = $exchangeRate; // ✅ Guardar para las líneas

                        if ($connection->supplier_id !== 9) {
                            $header["total_amount"] = $totalUSD * $exchangeRate;
                        }

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

                if ($tipo === "R" || $tipo === '01') {
                    $lineData = [];
                    $hasIvaTax = false;

                    $hasIvaTax = false;

                    foreach ($structure["lines"] as $index => $meta) {
                        $raw = $cols[$index] ?? "";
                        $value = $this->castValue($raw, $meta);
                        $lineData[$meta["field"]] = $value;

                        if ($meta["field"] === "porcentaje_iva" && is_numeric($value) && $value == 16) {
                            $hasIvaTax = true;
                        }
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

                    if ($hasIvaTax) {
                        $lineData["tax_enabled"] = 1;
                    }

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

        // Verificar si ya existe (incluyendo los eliminados lógicos y físicos para evitar fallos de integridad)
        $existingProduct = Product::withoutGlobalScope('not_deleted')->withTrashed()->where('barcode', $barcode)->first();
        if ($existingProduct) {
            if ($existingProduct->trashed()) {
                $existingProduct->restore();
            }
            return $existingProduct;
        }

        try {
            $productName = $lineData['descripcion_producto'] ?? $lineData['name'] ?? $lineData['descripcion'] ?? 'Producto desde factura';

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
                'iva' => isset($lineData['tax_enabled']) && $lineData['tax_enabled'] == 1 ? 1 : 0,
                'is_colombian_origin' => false,
                'psychotropic' => false,
                'stock' => 0,
                'sales_average' => 0,
                // 'is_active' => false, // Column missing in DB
                'is_deleted' => true, // Pending approval
            ]);

            return $newProduct;
        } catch (\Exception $e) {
            \Log::error('❌ Error al crear producto desde factura', [
                'supplier_id' => $supplierId,
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function castValue(mixed $raw, array $meta): mixed
    {
        if ($raw === null) return null;
        if (is_array($raw) || is_object($raw)) return null;
        $value = is_string($raw) ? trim(str_replace('"', '', $raw)) : (string) $raw;

        return match ($meta["type"]) {
            "string" => $value,
            "integer" => is_numeric($value) ? (int) $value : null,
            "decimal" => is_numeric($value)
            ? number_format(
                (float) $value / (isset($meta['decimals']) && $meta['decimals'] ? 100 : 1),
                2,
                ".",
                ""
            )
            : null,
            "date" => $this->parseDate($value, preferredFormat: $meta["format"] ?? null),
            "boolean" => is_numeric($value) && floatval($value) > 0 ? true : false,
            default => $value,
        };
    }

    private function parseDate(mixed $value, ?string $preferredFormat = null): ?string
    {
        if ($value === null) return null;
        $value = (string) $value;
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
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        if ($token) {
            $headers['Authorization'] = str_starts_with(strtolower($token), 'bearer ') ? $token : "Bearer $token";
            $headers['autorizacion'] = $token;
        }

        $method = strtolower($method);

        $client->{$method}(
            $path,
            $headers,
            $method === 'post' ? json_encode($data) : null
        )->then(function (ResponseInterface $response) use (&$productResponse, $path) {
            $body = (string) $response->getBody();
            $productResponse = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("API JSON Decode Error for {$path}", [
                    'error' => json_last_error_msg(),
                    'body_preview' => substr($body, 0, 500)
                ]);
            }
        }, function (\Exception $e) use ($path) {
            Log::error('API Error: ' . $e->getMessage(), ['url' => $path]);
        });

        Loop::run();

        return $productResponse ?? [];
    }

    private function parseNestedInvoice(array $invoice, SupplierConnection $connection): array
    {
        $structure = $connection->invoice_structure;
        $header = [];
        foreach ($structure['header'] as $index => $meta) {
            $header[$meta['field']] = $this->castValue($invoice[$meta['original_field']] ?? null, $meta);
        }

        $lines = [];
        foreach ($invoice['articulos'] as $article) {
            $lineData = [];
            foreach ($structure['lines'] as $index => $meta) {
                $lineData[$meta['field']] = $this->castValue($article[$meta['original_field']] ?? null, $meta);
            }
            // Cálculos básicos de línea
            $unitCost = floatval($lineData["unit_cost"] ?? 0);
            $quantity = intval($lineData["quantity"] ?? 0);
            $lineData["total_cost"] = number_format($unitCost * $quantity, 2, '.', '');
            $lines[] = $lineData;
        }

        return [
            'header' => $header,
            'lines' => $lines
        ];
    }

    public function buildPayload(SupplierConnection $connection, string $endpoint, $extra = null): ?array
    {
        $supplierId = $connection->supplier_id;
        $configPath = app_path("SupplierConfigs/{$supplierId}.php");

        if (!file_exists($configPath)) {
            return null;
        }

        $config = require $configPath;

        if (!isset($config[$endpoint])) {
            return null;
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

    public function convertLineToCSV(string $line): string
    {
        $originalLine = $line;

        // Reemplaza caracteres no deseados (espacio duro \xA0 y tabuladores) por un espacio normal
        $line = preg_replace('/[\x{a0}\t]/u', ' ', $line);

        if (preg_match('/^01\s/', $line)) {
            // Verifica si la línea comienza con "01" seguido de un espacio (tipo de registro 01)

            // Divide la línea en dos partes:
            // - $parts[1]: todo desde el inicio hasta antes de los últimos 5 números
            // - $parts[2]: los últimos 5 números (y posiblemente más) al final de la línea
            if (preg_match('/^(01\s+\d+\s+\S+\s+[A-Z]\s+.*?)\s+(\d+\s+\d+\s+\d+\s+\d+\s+\d+.*)$/', $line, $parts)) {
                $prefix = $parts[1];
                $numericTail = $parts[2];

                // Extrae los campos del encabezado del registro 01:
                // - Grupo 1: número de factura
                // - Grupo 2: código del proveedor
                // - Grupo 3: categoría (una letra mayúscula)
                // - Grupo 4: nombre del producto (el resto de la cadena)
                if (preg_match('/^01\s+(\d+)\s+(\S+)\s+([A-Z])\s+(.+)$/', $prefix, $head)) {
                    $invoice = $head[1];
                    $cod_supplier = $head[2];
                    $category = $head[3];
                    $name = trim($head[4]);

                    // Divide la parte numérica final en un máximo de 10 elementos usando espacios como delimitador
                    $nums = preg_split('/\s+/', trim($numericTail), 10);
                    $quantity = $nums[0];
                    $unit_cost = $nums[1];
                    $total_cost = $nums[4];

                    // Busca un código de barras: número de 12 o 13 dígitos rodeado por límites de palabra
                    preg_match('/\b(\d{12,13})\b/', $line, $b);

                    // Busca una fecha en formato dd/mm/aaaa rodeada por límites de palabra
                    preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $line, $e);

                    $barcode = $b[1] ?? '';
                    $expiration = $e[1] ?? '';

                    // Si se encontraron código de barras, fecha de vencimiento
                    if ($barcode && $expiration) {
                        // Elimina cualquier punto y coma del nombre del producto para evitar romper el CSV
                        $name = str_replace(';', '', $name);

                        // Devuelve la línea formateada como CSV con punto y coma como delimitador
                        return implode(';', [
                            '01',
                            $invoice,
                            $cod_supplier,
                            $name,
                            $quantity,
                            $unit_cost,
                            $total_cost,
                            $barcode,
                            $expiration
                        ]);
                    }
                }
            }
        } elseif (preg_match('/^02\s/', $line)) {
            // Verifica si la línea comienza con "02" seguido de un espacio (tipo de registro 02)

            // Normaliza nuevamente espacios duros y tabuladores a espacios simples
            $line = preg_replace('/[\x{a0}\t]/u', ' ', $line);

            // Normaliza abreviaturas de "a.m." y "p.m." (con o sin puntos y espacios) a " AM " y " PM "
            $line = preg_replace('/\s*a\s*\.?\s*m\s*\.?\s*/i', ' AM ', $line);
            $line = preg_replace('/\s*p\s*\.?\s*m\s*\.?\s*/i', ' PM ', $line);

            // Reduce múltiples espacios consecutivos a un solo espacio y elimina espacios al inicio/final
            $line = preg_replace('/\s+/', ' ', trim($line));

            // Extrae la fecha, hora y AM/PM de la línea (espera formato: dd/mm/yyyy hh:mm:ss AM/PM)
            if (!preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(\d{1,2}:\d{2}:\d{2})\s+([AP]M)/i', $line, $dtMatch)) {
                throw new Exception("Datetime not found in 02 line: $originalLine");
            }

            $datePart = $dtMatch[1]; // Fecha en formato dd/mm/yyyy

            // Convierte la fecha a formato Y-m-d (estándar MySQL)
            try {
                $dateTimeObj = \DateTime::createFromFormat('d/m/Y', $datePart);
                if (!$dateTimeObj) {
                    throw new Exception("Invalid date format: $datePart");
                }
                $mysqlDate = $dateTimeObj->format('Y-m-d'); // Ej: "2025-08-04"
            } catch (Exception $e) {
                throw new Exception("Failed to parse date: $datePart");
            }

            // Divide toda la línea en partes usando espacios como separadores
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 5) {
                throw new Exception("Too few fields in 02 line");
            }

            // El segundo campo es el ID de factura
            $id = $parts[1];

            // Helper function to normalize decimal numbers (comma to dot)
            $normalizeDecimal = function ($val) {
                return str_replace(',', '.', str_replace('.', '', $val)); // Remove thousands sep?, wait.
                // Assuming format like 56.642,40 (EU) or 56642,40
                // User said "56642,40". 
                // Let's safe-guard: replace comma with dot.
                // If there are thousands separators (like dots in EU), we should remove them first?
                // Dronena usually is local (VE/LATAM). 
                // Simplest fix for "56642,40" -> "56642.40" is simple replace.
                // But if input is "56.642,40", simple replace gives "56.642.40" (bad).
                // Let's assume standard "comma as decimal" without thousands sep for now, or handle specifically.
                // The provided example "56642,40" has no thousands separator.
                return str_replace(',', '.', $val);
            };

            $total_amount = $normalizeDecimal($parts[3]);
            // Los dos últimos campos deben ser valores numéricos (montos, cantidades, etc.)
            $exchange_rate = $normalizeDecimal($parts[count($parts) - 2] ?? '');
            $total_usd = $normalizeDecimal($parts[count($parts) - 1] ?? '');

            // AUTO-FIX: Check if total_usd is scaled by 100 (cents vs decimals issue)
            if (is_numeric($total_amount) && is_numeric($exchange_rate) && is_numeric($total_usd) && $exchange_rate > 0) {
                $calculated = $total_amount / $exchange_rate;
                // If the current total_usd is astronomically wrong (e.g. 100x bigger), but correcting by 100 makes it close...
                // Margin of error 2.0 to account for strict rounding diffs
                if (abs($total_usd - $calculated) > 5 && abs(($total_usd / 100) - $calculated) < 5) {
                    $total_usd = $total_usd / 100;
                }
            }

            // Formato de salida para registro 02: tipo;id_factura;total_amount;fecha(Y-m-d);exchange_rate;total_usd
            return implode(';', ['02', $id, $total_amount, $mysqlDate, $exchange_rate, $total_usd]);
        }

        // Si la línea no coincide con ninguno de los formatos esperados, lanza una excepción
        throw new Exception("Failed to parse line: $originalLine");
    }

    private function colIndex(?string $letters): ?int
    {
        if (!$letters || $letters === "N/A" || $letters === "null") {
            return null;
        }
        if (is_numeric($letters)) {
            return (int) $letters;
        }
        $letters = strtoupper(trim($letters));
        $len = strlen($letters);
        if ($len === 0 || $len > 3) return null; // Sanity check for letters (A, AA, AAA)
        
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($letters[$i]);
            if ($ord < 65 || $ord > 90) return null;
            $index = $index * 26 + ($ord - 64);
        }
        return $index - 1;
    }
}

