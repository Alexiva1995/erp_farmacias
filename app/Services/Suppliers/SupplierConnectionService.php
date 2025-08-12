<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
            Crypt::decryptString($connection->password),
        );
        if (!$login) {
            throw new \Exception("Credenciales inválidas");
        }

        ftp_pasv($ftp, true); // Modo pasivo

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
        return [];
    }

    public function parseDynamicContent(
        string $content,
        SupplierConnection $connection,
    ) {
        $now = now();
        $supplierId = $connection->supplier_id;
        $structureMap = [
            1 => ["db" => "barcode_match", "type" => "string"],
            2 => ["db" => "name", "type" => "string"],
            3 => ["db" => "expiration", "type" => "date"],
            4 => ["db" => "unit_cost", "type" => "decimal"],
        ];

        $lines = array_filter(explode("\n", trim($content)), "trim");
        $barcodes = [];

        foreach ($lines as $line) {
            $cols = explode(";", $line);
            $barcodes[] = trim($cols[1] ?? "");
        }

        $barcodes = array_unique(array_filter($barcodes));
        $products = Product::with("laboratory")
            ->whereIn("barcode", $barcodes)
            ->get()
            ->keyBy("barcode");

        $result = collect($lines)->map(function (string $line) use (
            $structureMap,
            $now,
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
            ];

            foreach ($structureMap as $index => $meta) {
                $raw = $cols[$index] ?? "";
                $value = trim($raw);

                switch ($meta["type"]) {
                    case "string":
                        $entry[$meta["db"]] = $value;
                        break;

                    case "decimal":
                        $entry[$meta["db"]] = is_numeric($value)
                            ? number_format((float) $value, 2, ".", "")
                            : null;
                        break;

                    case "date":
                        $entry[$meta["db"]] =
                            $value === ""
                                ? null
                                : \DateTime::createFromFormat(
                                    "d/m/Y",
                                    $value,
                                )?->format("Y-m-d");
                        break;
                }

                if ($meta["db"] === "barcode_match" && $value !== "") {
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
