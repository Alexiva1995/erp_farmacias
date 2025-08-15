<?php

namespace App\Services\Suppliers;

use App\Jobs\FetchSupplierConnectionData;
use App\Models\Product;
use App\Models\SupplierConnection;
use App\Helpers\FtpCrypt;
use Illuminate\Support\Facades\Http;

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

        $login = ftp_login(
            $ftp,
            $connection->username,
            FtpCrypt::decrypt($connection->password),
        );

        if (!$login) {
            throw new \Exception("Credenciales inválidas");
        }

        ftp_pasv($ftp, $connection->pasv); // Modo pasivo

        $tempFile = tempnam(sys_get_temp_dir(), "ftp_");
        if (ftp_get($ftp, $tempFile, $connection->path, FTP_BINARY)) {
            $content = file_get_contents($tempFile);
            $content_encoded = mb_convert_encoding(
                $content,
                "UTF-8",
                "ISO-8859-1",
            ); // Convierte a UTF-8 para devolver los resultados como JSON correctamente
            ftp_close($ftp);
            return $this->parseDynamicContent($content_encoded, $connection);
        } else {
            ftp_close($ftp);
            throw new \Exception("No se pudo descargar el archivo");
        }
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
                ->timeout(600)
                ->get($connection->path);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception("La petición a la API falló");
            }
        } catch (\Exception $e) {
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

        $barcodeKey = collect($structure)->search(fn($f) => ($f["target"] ?? null) === "barcode_match");

        foreach ($lines as $line) {
            $cols = explode(";", $line);
            $barcodes[] = trim($cols[$barcodeKey] ?? "");
        }

        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with("laboratory")->whereIn("barcode", $barcodes)->get()->keyBy("barcode");

        $result = collect($lines)->map(function (string $line) use ($structure, $now, $supplierId, $products) {
            $cols = explode(";", $line);
            $entry = [
                "supplier_id" => $supplierId,
                "created_at" => $now,
                "updated_at" => $now,
                "connection_date" => $now,
                "laboratory" => null,
                "product_id" => null,
            ];

            $table_structure = collect($structure)->filter(fn($f) => $f["target"] ?? null);

            foreach ($table_structure as $index => $meta) {
                $raw = $cols[$index] ?? "";
                $value = trim($raw);

                switch ($meta["type"]) {
                    case "string":
                        $entry[$meta["target"]] = $value;
                        break;

                    case "decimal":
                        $entry[$meta["target"]] = is_numeric($value) ? number_format((float) $value, 2, ".", "") : null;
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
}
