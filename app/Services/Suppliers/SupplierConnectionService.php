<?php

namespace App\Services\Suppliers;

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
        $structure = [
            ["type" => "string", "name" => "codigo_producto"],
            ["type" => "string", "name" => "codigo_barras"],
            ["type" => "string", "name" => "descripcion_producto"],
            ["type" => "date", "name" => "fecha_lote"],
            ["type" => "decimal", "name" => "precio_unitario_final"],
        ];
        $lines = explode("\n", trim($content));
        $result = [];

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === "") {
                continue;
            }

            $fields = explode(";", $line);
            $entry = [];

            $entry["supplier_id"] = $connection->supplier_id;
            $now = now();
            $entry["created_at"] = $now;
            $entry["updated_at"] = $now;
            $entry["connection_date"] = $now;
            $entry["laboratory"] = null;

            foreach ($structure as $index => $column) {
                $raw = $fields[$index] ?? null;
                $value = trim($raw);

                $db_column["name"] = match ($column["name"]) {
                    "codigo_producto" => "product_id",
                    "codigo_barras" => "barcode_match",
                    "descripcion_producto" => "name",
                    "fecha_lote" => "expiration",
                    "precio_unitario_final" => "unit_cost",
                };

                if ($column["type"] === "date" && $value == "") {
                    $entry[$db_column["name"]] = null;
                } else {
                    switch ($column["type"]) {
                        case "string":
                            $entry[$db_column["name"]] = $value;
                            break;

                        case "int":
                            $entry[$db_column["name"]] = is_numeric($value)
                                ? (int) $value
                                : null;
                            break;

                        case "decimal":
                            $entry[$db_column["name"]] = is_numeric($value)
                                ? number_format((float) $value, 2, ".", "")
                                : null;
                            break;

                        case "date":
                            if ($value === "") {
                                $entry[$db_column["name"]] = null;
                            }

                            $entry[
                                $db_column["name"]
                            ] = \DateTime::createFromFormat(
                                    "d/m/Y",
                                    $value,
                                )?->format("Y-m-d");
                            break;

                        default:
                            $entry[$db_column["name"]] = $value;
                    }
                }
            }

            $result[] = $entry;
        }

        return $result;
    }
}
