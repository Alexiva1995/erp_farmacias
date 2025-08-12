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
    public function fetchData(SupplierConnection $connection){
        switch ($connection->type) {
            case 'ftp':
                return $this->fetchFromFtp($connection);
            case 'sftp':
                return $this->fetchFromSftp($connection);
            case 'http':
            case 'api':
                return $this->fetchFromHttp($connection);
            default:
                throw new \Exception("Tipo de conexión no soportado");
        }
    }

    public function fetchFromFtp(SupplierConnection $connection)
    {
        $ftp = ftp_connect($connection->host, $connection->port ?? 21, 10);
        if (!$ftp) throw new \Exception("No se pudo conectar al servidor FTP");

        $login = ftp_login($ftp, $connection->username, Crypt::decryptString($connection->password));
        if (!$login) throw new \Exception("Credenciales inválidas");

        ftp_pasv($ftp, true); // Modo pasivo

        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        if (ftp_get($ftp, $tempFile, $connection->path, FTP_BINARY)) {
            $content = file_get_contents($tempFile);
            ftp_close($ftp);
            dd($content);
            //return $this->parseDynamicContent($content);
        } else {
            ftp_close($ftp);
            throw new \Exception("No se pudo descargar el archivo");
        }
    }

    public function fetchFromSftp(SupplierConnection $connection){
        return [];
    }

    public function fetchFromHttp(SupplierConnection $connection){
        return [];
    }

    public function parseDynamicContent(string $content, array $structure): array
    {
        $lines = explode("\n", trim($content));
        $result = [];

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') continue;

            $fields = explode(';', $line);
            $entry = [];

            foreach ($structure as $index => $column) {
                $raw = $fields[$index] ?? null;
                $value = trim($raw);

                switch ($column['type']) {
                    case 'string':
                        $entry[$column['name']] = $value;
                        break;

                    case 'int':
                        $entry[$column['name']] = is_numeric($value) ? (int)$value : null;
                        break;

                    case 'decimal':
                        $entry[$column['name']] = is_numeric($value)
                            ? number_format((float)$value, 2, '.', '')
                            : null;
                        break;

                    case 'date':
                        $entry[$column['name']] = \DateTime::createFromFormat('d/m/Y', $value)?->format('Y-m-d');
                        break;

                    default:
                        $entry[$column['name']] = $value;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }
}
