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

            // Optimización: Cargar facturas ya registradas en base de datos para no descargarlas repetidamente por FTP
            $existingInvoicesMap = \App\Models\Invoice::where('supplier_id', $connection->supplier_id)
                ->pluck('invoice_number')
                ->map(fn($num) => strtoupper(trim((string)$num)))
                ->filter()
                ->flip()
                ->toArray();

            // Filtrar archivos cuya factura ya esté registrada en BD por nombre de archivo
            $filesToProcess = [];
            foreach ($files as $filePath) {
                if (!str_ends_with(strtolower($filePath), ".txt")) {
                    continue;
                }
                $filename = pathinfo($filePath, PATHINFO_FILENAME);
                $cleanFilename = strtoupper(trim($filename));
                $cleanFilenameNoPrefix = strtoupper(ltrim($cleanFilename, 'F'));
                $cleanFilenameNoZeroes = strtoupper(ltrim($cleanFilenameNoPrefix, '0'));

                if (isset($existingInvoicesMap[$cleanFilename]) || 
                    isset($existingInvoicesMap[$cleanFilenameNoPrefix]) ||
                    (!empty($cleanFilenameNoZeroes) && isset($existingInvoicesMap[$cleanFilenameNoZeroes]))) {
                    // Ya está en BD, saltar descarga FTP
                    continue;
                }

                $filesToProcess[] = $filePath;
            }

            Log::info("📡 [FTP FACTURAS] Proveedor {$connection->supplier_id}: Total archivos remotos: " . count($files) . ", Nuevos a descargar: " . count($filesToProcess));

            foreach ($filesToProcess as $filePath) {
                $tempInvoice = tempnam(sys_get_temp_dir(), "inv_");

                if (@ftp_get($ftp, $tempInvoice, $filePath, FTP_BINARY)) {
                    $filename = pathinfo($filePath, PATHINFO_FILENAME);
                    $invoiceContent = file_get_contents($tempInvoice);
                    $isVitalClinic = str_contains(strtolower($connection->host ?? ''), 'vitalclinic')
                        || str_contains(strtolower($connection->supplier?->name ?? ''), 'vitalclinic')
                        || in_array($connection->supplier_id, [2, 1009]);
                    $parsed = $this->invoiceTxtParser($invoiceContent, $connection, $seenInvoiceNumbers, $isVitalClinic ? $filename : null);

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
            $isCristmedicals = str_contains(strtolower($connection->host ?? ''), 'cristmedicals')
                || str_contains(strtolower($connection->supplier?->name ?? ''), 'crist')
                || in_array($connection->supplier_id, [3, 21, 1002]);

            if (!empty($connection->username) && !empty($connection->password) && !$isCristmedicals) {
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

            // Determinar URL de productos: desde payloadDef o construida desde host + path
            $url = $payloadDef['url'] ?? null;
            if (!$url && !empty($connection->path) && (str_starts_with($connection->path, 'http://') || str_starts_with($connection->path, 'https://'))) {
                $url = $connection->path;
            } elseif (!$url && !empty($connection->host)) {
                $base = rtrim($connection->host, '/');
                $path = ltrim($connection->path ?? '', '/');
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $url = $path;
                } else {
                    $url = !empty($path) ? "{$base}/{$path}" : $base;
                }
            } elseif (!$url && !empty($connection->path)) {
                $url = $connection->path;
            }

            if (!empty($url)) {
        $requestData = isset($payloadDef['payload']) ? $payloadDef['payload'] : (isset($payloadDef['url']) ? [] : ($payloadDef ?? []));
                $method = $payloadDef['method'] ?? 'get';

                $allProducts = [];
                $currentUrl = $url;
                
                while ($currentUrl) {
                    $productResponse = $this->fetchFromAPI($token, $requestData, $client, $currentUrl, $method);
                    
                    Log::info("📡 [API PRODUCTOS] Respuesta de {$currentUrl}", [
                        'type' => gettype($productResponse),
                        'keys' => is_array($productResponse) ? array_keys($productResponse) : [],
                        'count' => is_countable($productResponse) ? count($productResponse) : 0,
                    ]);
                    
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
                    } elseif (isset($productResponse['productos']) && is_array($productResponse['productos'])) {
                        $pageData = $productResponse['productos'];
                    } elseif (isset($productResponse['items']) && is_array($productResponse['items'])) {
                        $pageData = $productResponse['items'];
                    } elseif (isset($productResponse['data']) && is_array($productResponse['data'])) {
                         $pageData = $productResponse['data'];
                         $nextPageUrl = $productResponse['next_page_url'] ?? null;
                    } elseif (isset($productResponse['results']) && is_array($productResponse['results'])) {
                        $pageData = $productResponse['results'];
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

                Log::info("📡 [API PRODUCTOS] Total productos recopilados: " . count($allProducts));
                $productCsvString = $this->convertJsonArrayToCsvString($allProducts);
                $productData = $this->parseDynamicContent($productCsvString, $connection);
                Log::info("📡 [API PRODUCTOS] Total productos parseados: " . count($productData ?? []));
            }

            // Facturas (si tiene ruta definida)
            $invoiceResults = [];
            if (!empty($connection->invoice_path)) {
                $seenInvoiceNumbers = [];

                $payloadInvoice = $this->buildPayload($connection, 'facturas');
                $invoiceUrl = $payloadInvoice['url'] ?? null;
                if (!$invoiceUrl && !empty($connection->invoice_path) && (str_starts_with($connection->invoice_path, 'http://') || str_starts_with($connection->invoice_path, 'https://'))) {
                    $invoiceUrl = $connection->invoice_path;
                } elseif (!$invoiceUrl && !empty($connection->host)) {
                    $base = rtrim($connection->host, '/');
                    $path = ltrim($connection->invoice_path, '/');
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        $invoiceUrl = $path;
                    } else {
                        $invoiceUrl = !empty($path) ? "{$base}/{$path}" : $base;
                    }
                } elseif (!$invoiceUrl) {
                    $invoiceUrl = $connection->invoice_path;
                }

                $requestDataInv = isset($payloadInvoice['payload']) ? $payloadInvoice['payload'] : (isset($payloadInvoice['url']) ? [] : ($payloadInvoice ?? []));
                $invMethod = $payloadInvoice['method'] ?? 'get';
                
                Log::info("🔎 [FACTURAS] buildPayload result", ['payloadInvoice' => $payloadInvoice, 'invoiceUrl' => $invoiceUrl, 'method' => $invMethod]);
                $invoiceResponse = $this->fetchFromAPI($token, $requestDataInv, $client, $invoiceUrl, $invMethod);
                Log::info("🔎 [FACTURAS] fetchFromAPI result", ['count' => is_countable($invoiceResponse) ? count($invoiceResponse) : 0]);

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

        // Calcular offsets de columna desde la cabecera si el archivo es de ancho fijo/espaciado
        $headerOffsets = [];
        if ($has_header && !empty($headerLine) && !str_contains($headerLine, ';') && !str_contains($headerLine, "\t")) {
            preg_match_all('/\S+/', $headerLine, $matches, PREG_OFFSET_CAPTURE);
            if (!empty($matches[0]) && count($matches[0]) > 1) {
                $headerOffsets = array_map(fn($m) => $m[1], $matches[0]);
            }
        }

        $splitLine = function (string $l) use ($headerOffsets) {
            if (str_contains($l, ';')) {
                return explode(';', $l);
            }
            if (str_contains($l, "\t")) {
                return explode("\t", $l);
            }
            if (!empty($headerOffsets)) {
                $cols = [];
                $count = count($headerOffsets);
                for ($i = 0; $i < $count; $i++) {
                    $start = $headerOffsets[$i];
                    $length = isset($headerOffsets[$i + 1]) ? ($headerOffsets[$i + 1] - $start) : null;
                    if ($start < strlen($l)) {
                        $cols[] = trim($length !== null ? substr($l, $start, $length) : substr($l, $start));
                    } else {
                        $cols[] = '';
                    }
                }
                return $cols;
            }
            return preg_split('/\s{2,}/', trim($l)) ?: [$l];
        };

        if ($has_header && !empty($headerLine)) {
            if (!empty($structure_for_parsing)) {
                $headerLine = $this->parseFixedWidth($headerLine, $structure_for_parsing);
            }
            // Remove BOM if present
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
            $headers = $splitLine($headerLine);
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
            $cols = $splitLine($line);

            if ($barcodeIdx !== null && isset($cols[$barcodeIdx])) {
                $barcodes[] = trim($cols[$barcodeIdx] ?? "");
            }
        }

        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with(['laboratory' => fn($q) => $q->select(['id', 'name'])])
            ->whereIn("barcode", $barcodes)
            ->select(['id', 'barcode', 'laboratory_id'])
            ->get()
            ->keyBy("barcode");

        $result = collect($lines)->map(function (string $line, $key) use ($normalizedStructure, $now, $usdCurrency, $supplierId, $products, $structure_for_parsing, $headerMap, $getIdx, $splitLine) {
            if (!empty($structure_for_parsing)) {
                $line = $this->parseFixedWidth($line, $structure_for_parsing);
            }
            $cols = $splitLine($line);
            
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
                        $trimmedVal = trim($value);
                        $trimmedVal = preg_replace('/[^\d.,-]/', '', $trimmedVal);
                        if (empty($trimmedVal)) {
                            $cleanValue = "0.00";
                        } elseif (str_contains($trimmedVal, '.') && str_contains($trimmedVal, ',')) {
                            $cleanValue = str_replace(',', '.', str_replace('.', '', $trimmedVal));
                        } elseif (str_contains($trimmedVal, ',')) {
                            $cleanValue = str_replace(',', '.', $trimmedVal);
                        } else {
                            if (preg_match('/^\d{1,3}\.\d{3}$/', $trimmedVal)) {
                                $cleanValue = str_replace('.', '', $trimmedVal);
                            } else {
                                $cleanValue = $trimmedVal;
                            }
                        }
                        if (is_numeric($cleanValue)) {
                            $newValue = number_format((float) $cleanValue, 2, ".", "");

                            if (in_array($meta["target"], ["exisMerida", "exisCaracas", "exisOriente", "quantity"])) {
                                $quantity += (float) $cleanValue;
                                break;
                            }

                            // Si ya tiene la columna especifica unit_cost_usd en el archivo
                            if ($hasUnitCostUsd) {
                                $entry[$meta["target"]] = $newValue;
                                break;
                            } else {
                                if (isset($meta["currency"]) && $meta["currency"] === "usd") {
                                    $entry[$meta["target"]] = number_format(
                                        (float) ($newValue * ($usdCurrency->rate ?? 1)),
                                        2,
                                        ".",
                                        ""
                                    );
                                    if ($meta["target"] === "unit_cost") {
                                        $entry["unit_cost_usd"] = $newValue;
                                    }
                                    break;
                                } else {
                                    $entry[$meta["target"]] = $newValue;
                                    // Solo calcular unit_cost_usd si la columna actual es el costo unitario (unit_cost)
                                    if ($meta["target"] === "unit_cost") {
                                        $entry["unit_cost_usd"] = number_format(
                                            (float) ($newValue / ($usdCurrency->rate ?? 1)),
                                            2,
                                            ".",
                                            ""
                                        );
                                    }
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
                        $entry["laboratory"] = $product?->laboratory?->name ?? $entry["laboratory"] ?? null;
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

            $entry["unit_cost_with_discount"] = $entry["unit_cost"] ?? null;
            $entry["unit_cost_usd_with_discount"] = $entry["unit_cost_usd"] ?? null;

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

                $isVitalClinic = str_contains(strtolower($connection->host ?? ''), 'vitalclinic')
                    || str_contains(strtolower($connection->supplier?->name ?? ''), 'vitalclinic')
                    || in_array($connection->supplier_id, [2, 1009]);

                if ($isVitalClinic) {
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

            $isDronena = str_contains(strtolower($connection->host ?? ''), 'dronena')
                || str_contains(strtolower($connection->supplier?->name ?? ''), 'dronena')
                || in_array($connection->supplier_id, [27, 1014]);
            $isDromega = str_contains(strtolower($connection->host ?? ''), 'dromega')
                || str_contains(strtolower($connection->supplier?->name ?? ''), 'dromega')
                || in_array($connection->supplier_id, [9, 15, 38, 1005]);

            foreach ($lines as $line) {
                $cols = explode($separator, $line);
                if ($isDronena) {
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

                    if ($isDronena) {
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

                    if ($isDromega) {
                        $totalUSD = floatval($header["total_usd"] ?? 0);
                        $exchangeRate = floatval($header["exchange_rate"] ?? 0);
                        $currentExchangeRate = $exchangeRate; // ✅ Guardar para las líneas

                        if ($connection->supplier_id !== 9 && $connection->supplier_id !== 1005) {
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
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        if ($token) {
            $headers['Authorization'] = str_starts_with(strtolower($token), 'bearer ') ? $token : "Bearer $token";
            $headers['autorizacion'] = $token;
        }

        $method = strtolower($method);

        try {
            $http = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(120);

            if ($method === 'post') {
                $response = $http->post($path, is_array($data) ? $data : []);
            } else {
                $response = $http->get($path, is_array($data) && !empty($data) ? $data : []);
            }

            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    return $json;
                }
                $decoded = json_decode($response->body(), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } else {
                Log::warning("API Request returned status {$response->status()} for {$path}", [
                    'body_preview' => substr($response->body(), 0, 500)
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Http:: client error for {$path}: " . $e->getMessage());
        }

        $productResponse = [];
        try {
            $client->{$method}(
                $path,
                $headers,
                $method === 'post' ? json_encode($data) : null
            )->then(function (ResponseInterface $response) use (&$productResponse, $path) {
                $body = (string) $response->getBody();
                $productResponse = json_decode($body, true);
            }, function (\Exception $e) use ($path) {
                Log::error('ReactPHP API Error: ' . $e->getMessage(), ['url' => $path]);
            });

            Loop::run();
        } catch (\Throwable $e) {
            // silenciar
        }

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
        $supplier = $connection->supplier ?? \App\Models\Supplier::find($supplierId);
        $supplierName = strtolower($supplier?->name ?? '');
        $host = strtolower($connection->host ?? '');

        $candidates = [
            app_path("SupplierConfigs/{$supplierId}.php"),
        ];

        if (!empty($supplierName)) {
            $candidates[] = app_path("SupplierConfigs/" . \Illuminate\Support\Str::slug($supplierName, '') . ".php");
            $candidates[] = app_path("SupplierConfigs/" . \Illuminate\Support\Str::slug($supplierName, '_') . ".php");
            $candidates[] = app_path("SupplierConfigs/" . \Illuminate\Support\Str::slug($supplierName, '-') . ".php");
        }

        // Aliases por contenido de nombre o host
        if (str_contains($host, 'cristmedicals') || str_contains($supplierName, 'crist')) {
            $candidates[] = app_path("SupplierConfigs/cristalmedicals.php");
            $candidates[] = app_path("SupplierConfigs/cristmedicals.php");
            $candidates[] = app_path("SupplierConfigs/1002.php");
            $candidates[] = app_path("SupplierConfigs/3.php");
            $candidates[] = app_path("SupplierConfigs/21.php");
        }

        if (str_contains($host, 'cobeca') || str_contains($supplierName, 'mafarta') || str_contains($supplierName, 'cobeca')) {
            $candidates[] = app_path("SupplierConfigs/cobeca.php");
            $candidates[] = app_path("SupplierConfigs/mafarta.php");
            $candidates[] = app_path("SupplierConfigs/drogueriascobeca.php");
            $candidates[] = app_path("SupplierConfigs/1011.php");
            $candidates[] = app_path("SupplierConfigs/23.php");
        }

        $configPath = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $configPath = $candidate;
                break;
            }
        }

        if (!$configPath) {
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
        $first = (array) ($data[0] ?? []);
        fputcsv($csv, array_keys($first), ';');

        // Escribir filas
        foreach ($data as $row) {
            $rowArray = (array) $row;
            fputcsv($csv, array_map(function ($value) {
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }, $rowArray), ';');
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

