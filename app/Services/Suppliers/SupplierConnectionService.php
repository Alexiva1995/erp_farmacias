<?php

namespace App\Services\Suppliers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Helpers\FtpCrypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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
                throw new \Exception("Tipo de conexión no soportado");
        }
    }

    public function fetchFromFtp(SupplierConnection $connection)
    {
        $ftp = ftp_connect($connection->host, $connection->port ?? 21, 10);
        if (!$ftp) {
            throw new \Exception("No se pudo conectar al servidor FTP");
        }

        $login = ftp_login($ftp, $connection->username, FtpCrypt::decrypt($connection->password));

        if (!$login) {
            throw new \Exception("Credenciales inválidas");
        }

        ftp_pasv($ftp, $connection->pasv); // Modo pasivo

        // Productos
        $tempFile = tempnam(sys_get_temp_dir(), "ftp_");
        if (ftp_get($ftp, $tempFile, $connection->path, FTP_BINARY)) {
            $content = file_get_contents($tempFile);
            $content_encoded = mb_convert_encoding($content, "UTF-8", "ISO-8859-1"); // Convierte a UTF-8 para devolver los resultados como JSON correctamente
            $productData = $this->parseDynamicContent($content_encoded, $connection);
        } else {
            throw new \Exception("No se pudo guardar los productos");
        }

        // Facturas (si tiene ruta definida)
        $invoiceResults = [];
        if (!empty($connection->invoice_path)) {
            $files = ftp_nlist($ftp, $connection->invoice_path);
            foreach ($files as $filePath) {
                if (!str_ends_with($filePath, ".txt")) {
                    continue;
                }
                $tempInvoice = tempnam(sys_get_temp_dir(), "inv_");

                if (ftp_get($ftp, $tempInvoice, $filePath, FTP_BINARY)) {
                    $invoiceContent = file_get_contents($tempInvoice);
                    $parsed = $this->invoiceTxtParser($invoiceContent, $connection);
                    $invoiceResults[] = $parsed;
                }
            }
        }

        ftp_close($ftp);

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
        $user = $connection->username;
        $password = FtpCrypt::decrypt($connection->password);

        $response = Http::post($connection->host, [
            "usuario" => $user,
            "clave" => $password,
        ]);
        $token = $response->json()["token"];

        try {
            $response = Http::withHeaders([
                "autorizacion" => $token,
            ])
                ->timeout(1000)
                ->get($connection->path);

            if ($response->successful()) {
                $productData = $this->parseDynamicContent($response->json(), $connection);
                return $productData;
            } else {
                throw new \Exception("La petición a la API falló");
            }
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
                \Log::error("Failed to fetch exchange rate");
                throw new \Exception("No se pudo guardar la tasa del día USD");
            }
        }

        $barcodeKey = collect($structure)->search(fn($f) => ($f["target"] ?? null) === "barcode_match");

        \Log::info("Data structure: ");
        \Log::info(explode(";", $lines[0]));
        foreach ($lines as $line) {
            $cols = explode(";", $line);
            $barcodes[] = trim($cols[$barcodeKey] ?? "");
        }

        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with("laboratory")->whereIn("barcode", $barcodes)->get()->keyBy("barcode");

        $result = collect($lines)->map(function (string $line) use (
            $structure,
            $now,
            $usdCurrency,
            $supplierId,
            $products,
        ) {
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

            foreach ($table_structure as $index => $meta) {
                $raw = $cols[$index] ?? "";
                $value = trim($raw);

                switch ($meta["type"]) {
                    case "string":
                        $entry[$meta["target"]] = $value;
                        break;

                    case "decimal":
                        if (is_numeric($value)) {
                            $newValue = number_format((float) $value, 2, ".", "");

                            if ($meta["target"] === "quantity") {
                                $entry[$meta["target"]] = $newValue;
                                break;
                            }

                            // Si ya tiene el precio en bs y usd
                            if ($hasUnitCostUsd) {
                                $entry[$meta["target"]] = $newValue;
                                break;
                            } else {
                                // Precio en bs calcula con la tasa  usd del dia
                                if ($meta["currency"] === "usd") {
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
                        if ($value === "0000-00-00") {
                            $entry[$meta["target"]] = null;
                            break;
                        }

                        if (\Datetime::createFromFormat("Y-m-d", $value)) {
                            $entry[$meta["target"]] = $value;
                            break;
                        }

                        if ($value === "NULL") {
                            $entry[$meta["target"]] = null;
                            break;
                        }

                        $entry[$meta["target"]] =
                            $value === "" ? null : \DateTime::createFromFormat("d/m/Y", $value)?->format("Y-m-d");
                        break;
                }

                if ($meta["target"] === "barcode_match" && $value !== "") {
                    $product = $products->get($value);
                    $entry["laboratory"] = $product?->laboratory?->name;
                    $entry["product_id"] = $product?->id;
                }
            }

            return $entry;
        });

        return $result->toArray();
    }

    public function invoiceTxtParser(string $content, SupplierConnection $connection): array
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

                $invoices = [
                    "header" => $header,
                    "lines" => $bufferLines,
                ];

                $bufferLines = []; // limpiar para el próximo bloque
            }

            if ($tipo === "R") {
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
        return $invoices;
    }

    private function castValue(string $raw, array $meta): mixed
    {
        $value = trim($raw);

        return match ($meta["type"]) {
            "string" => $value,
            "integer" => is_numeric($value) ? (int) $value : null,
            "decimal" => is_numeric($value) ? number_format((float) $value, 2, ".", "") : null,
            "date" => \DateTime::createFromFormat($meta["format"] ?? "Y-m-d", $value)?->format("Y-m-d"),
            default => $value,
        };
    }
}
