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
        $data = $this->fetchFromAPI($connection);
        if ($data) {
            $jsonPath = storage_path('app/api_products.json');
            $csvPath = storage_path('app/api_products.txt');

            // Verifica si el archivo se descargó
            if (!file_exists($jsonPath) || filesize($jsonPath) === 0)
                throw new Exception("Archivo no descargado");

            // Convierte JSON a CSV
            $data = json_decode(file_get_contents($jsonPath), true);
            if (!is_array($data) || empty($data)) {
                throw new Exception("Contenido JSON inválido");
            }

            try {
                $headers = array_keys($data[0]);
                $csv = implode(';', $headers) . "\n";

                foreach ($data as $row) {
                    $line = [];
                    foreach ($headers as $key) {
                        $value = $row[$key] ?? '';

                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        $value = str_replace(["\n", "\r", ";"], [" ", " ", ","], $value);
                        $line[] = $value;
                    }
                    $csv .= implode(';', $line) . "\n";
                }

                file_put_contents($csvPath, $csv);
                $productData = $this->parseDynamicContent(file_get_contents($csvPath), $connection);

                unlink($jsonPath);
                unlink($csvPath);

                return [
                    "products" => $productData ?? [],
                    "invoices" => $invoiceResults ?? [],
                ];
            } catch (Exception $e) {
                Log::alert("Supplier connection service");
                Log::error($e);
                throw new Exception("No se pudo establecer la conexión");
            }
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
                \Log::error("Failed to fetch exchange rate");
                throw new Exception("No se pudo guardar la tasa del día USD");
            }
        }

        $structure_for_parsing = json_decode($connection->parse_using);
        $barcodeKey = collect($structure)->search(fn($f) => ($f["target"] ?? null) === "barcode_match");

        foreach ($lines as $line) {
            if (!empty($structure_for_parsing)) {
                $line = $this->parseFixedWidth($line, $structure_for_parsing);
            }
            $cols = explode(';', $line);
            $barcodes[] = trim($cols[$barcodeKey] ?? "");
        }

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

            $quantity = 0;
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
                                $quantity += $newValue;
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

            $entry["quantity"] = $quantity;

            if ($missingBarcode && !Product::where('barcode', $entry['barcode_match'])->exists()) {
                $stock = 0;

                if ($entry['supplier_id'] == 2) {
                    foreach (['exisMerida', 'exisCaracas', 'exisOriente'] as $campo) {
                        $stock += intval($entry[$campo] ?? 0);
                    }
                } else
                    $stock = $entry['quantity'];

                $newProduct = Product::create([
                    'barcode' => $entry['barcode_match'],
                    'name' => $entry['name'] ?? 'Producto sin nombre',
                    'unit_cost' => $entry['unit_cost'] ?? 0,
                    'sale_price' => $entry['unit_cost'] ?? 0,
                    'stock' => $stock,
                    'active_ingredient' => $entry['active_ingredient'] ?? 'Producto FTP',
                    'sales_average' => $entry['sales_average'] ?? 0
                ]);

                $entry['product_id'] = $newProduct->id;

                $products->put($missingBarcode, $newProduct); // actualiza el cache local
            }

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
                $lineData['product_id'] = $products[$barcode]->id ?? null;

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
            foreach ($lines as $line) {
                $cols = explode($separator, $line);
                if ($separator === "\t") {
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

                    $invoiceNumber = $header['invoice_number'] ?? null;
                    if ($invoiceNumber && in_array($invoiceNumber, $seenInvoiceNumbers)) {
                        $bufferLines = []; // limpiar igual
                        continue; // ya existe, saltar
                    }

                    $invoices = [
                        "header" => $header,
                        "lines" => $bufferLines,
                    ];

                    $seenInvoiceNumbers[] = $invoiceNumber;
                    $bufferLines = []; // limpiar para el próximo bloque
                }

                if ($tipo === "R" || $tipo === '01') {
                    $lineData = [];

                    foreach ($structure["lines"] as $index => $meta) {
                        $raw = $cols[$index] ?? "";
                        $value = $this->castValue($raw, $meta);
                        $lineData[$meta["field"]] = $value;
                    }

                    $barcode = $lineData["barcode"] ?? null;
                    $lineData["product_id"] = $products[$barcode]->id ?? null;

                    $unitCost = floatval($lineData["unit_cost"] ?? 0);
                    $quantity = intval($lineData["quantity"] ?? 0);
                    $lineData["total_cost"] = $unitCost * $quantity;

                    $bufferLines[] = $lineData;
                }
            }
        }

        return $invoices;
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
        ]);

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format("Y-m-d");
            }
        }

        return null;
    }

    public function fetchFromAPI(SupplierConnection $connection): bool
    {
        $connector = new Connector([
            'timeout' => 1800,
        ]);

        $client = (new Browser($connector))->withTimeout(1800.0);
        $token = $this->getAPIToken($connection);
        $url = $connection->path;
        $path = storage_path('app/api_products.json');

        $success = false;

        try {
            $client->get($url, [
                'Autorizacion' => $token
            ])->then(
                    function (ResponseInterface $response) use (&$success, $path) {
                        file_put_contents($path, (string) $response->getBody());
                        $success = true;
                    },
                    function (Exception $e) use (&$success) {
                        Log::error('Error: ' . $e->getMessage() . PHP_EOL);
                    }
                );
        } catch (\Throwable $e) {
            Log::error('Error API: ' . $e->getMessage());
            $success = false;
        }

        Loop::run(); // ejecuta el ciclo de eventos

        return $success && file_exists($path) && filesize($path) > 0;
    }

    public function getAPIToken(SupplierConnection $connection): string
    {
        $user = $connection->username;
        $password = FtpCrypt::decrypt($connection->password);

        $loginResponse = Http::post($connection->host, [
            "usuario" => $user,
            "clave" => $password,
        ]);

        return $loginResponse->json()["token"];
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
                    $exisMerida = $nums[0];
                    $unit_cost_raw = $nums[1];
                    $exisCaracas = $nums[2];
                    $exisOriente = $nums[3];

                    // Busca un código de barras: número de 12 o 13 dígitos rodeado por límites de palabra
                    preg_match('/\b(\d{12,13})\b/', $line, $b);

                    // Busca una fecha en formato dd/mm/aaaa rodeada por límites de palabra
                    preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $line, $e);

                    $barcode = $b[1] ?? '';
                    $expiration = $e[1] ?? '';

                    // Si se encontraron código de barras, fecha de vencimiento y la existencia es numérica...
                    if ($barcode && $expiration && is_numeric($exisMerida)) {
                        // Elimina cualquier punto y coma del nombre del producto para evitar romper el CSV
                        $name = str_replace(';', '', $name);

                        // Devuelve la línea formateada como CSV con punto y coma como delimitador
                        return implode(';', [
                            '01',
                            $invoice,
                            $cod_supplier,
                            $category,
                            $name,
                            $exisMerida,
                            $unit_cost_raw,
                            $exisCaracas,
                            $exisOriente,
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
            // Los dos últimos campos deben ser valores numéricos (montos, cantidades, etc.)
            $last1 = $parts[count($parts) - 2] ?? '';
            $last2 = $parts[count($parts) - 1] ?? '';

            if (!is_numeric($last1) || !is_numeric($last2)) {
                throw new Exception("Final fields not numeric in 02 line");
            }

            // Formato de salida para registro 02: tipo;id_factura;campo_numerico_1;fecha(Y-m-d);campo_numerico_2
            return implode(';', ['02', $id, $last1, $mysqlDate, $last2]);
        }

        // Si la línea no coincide con ninguno de los formatos esperados, lanza una excepción
        throw new Exception("Failed to parse line: $originalLine");
    }
}

