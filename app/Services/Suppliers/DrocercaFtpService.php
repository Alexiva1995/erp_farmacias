<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DrocercaFtpServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Exception;
use Illuminate\Support\Facades\Log;

class DrocercaFtpService implements DrocercaFtpServiceInterface
{
    /**
     * Genera el contenido del archivo plano de pedido .txt según el manual de Drocerca:
     * Separador de campos: ;
     * Estructura por línea:
     * 1. Codigo: Código de producto Drocerca VARCHAR(15)
     * 2. Descripcion: Descripción de producto VARCHAR(25)
     * 3. Cantidad: DECIMAL(12,3) -> formateado con 3 decimales
     * 4. Precio: DECIMAL(12,3) -> precio unitario con 3 decimales
     * 5. Sede: Código/Número de sede (1=Mérida, 2=Centro/Caracas, 3=Oriente)
     * 6. Cod cli: Código de cliente Drocerca
     */
    public function generateOrderContent(AutoOrder $autoOrder, ?string $clientCode = null, int $sede = 1): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];
        $cliCode = $clientCode ?: 'W008B3';

        foreach ($autoOrder->details as $detail) {
            // 1. Código de producto asignado por Drocerca
            $code = $detail->productSupplier?->cod_supplier 
                ?? $detail->product?->barcode 
                ?? (string) $detail->product_id;
            $code = mb_substr(trim((string) $code), 0, 15);

            // 2. Descripción del producto (máx 25 caracteres según manual)
            $name = $detail->productSupplier?->name 
                ?? $detail->product?->name 
                ?? 'PRODUCTO';
            $name = mb_substr(trim((string) $name), 0, 25);

            // 3. Cantidad solicitada (DECIMAL 12,3)
            $quantity = number_format((float) $detail->quantity, 3, '.', '');

            // 4. Precio de producto (DECIMAL 12,3)
            $price = number_format((float) ($detail->unit_cost ?? 0), 3, '.', '');

            // 5. Sede Drocerca (1=Mérida, 2=Centro, 3=Oriente)
            $sedeVal = $sede;

            // 6. Código de cliente Drocerca
            $lines[] = "{$code};{$name};{$quantity};{$price};{$sedeVal};{$cliCode}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Conecta al servidor FTP de Drocerca y sube el archivo de pedido a la carpeta 'pedidos'.
     */
    public function sendOrderFtp(AutoOrder $autoOrder, int $sede = 1): array
    {
        $supplier = $autoOrder->supplier;
        if (!$supplier) {
            $supplier = Supplier::with('connections')->find($autoOrder->supplier_id);
        }

        if (!$supplier) {
            throw new Exception("No se encontró el proveedor asociado a la orden #{$autoOrder->id}");
        }

        // Buscar conexión FTP configurada para Drocerca
        $connection = $supplier->connections()
            ->where('type', 'ftp')
            ->first();

        if (!$connection) {
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where(function ($query) {
                    $query->where('host', 'LIKE', '%drocerca%')
                        ->orWhere('username', 'LIKE', '%drocerca%')
                        ->orWhere('username', 'LIKE', '%W008%');
                })
                ->first();
        }

        $host = $connection?->host ?: env('DROCERCA_FTP_HOST', 'drocerca.proteoerp.org');
        $port = (int) ($connection?->port ?? env('DROCERCA_FTP_PORT', 21));
        $user = (string) ($connection?->username ?? env('DROCERCA_FTP_USER'));
        $pass = (string) ($connection?->password ? FtpCrypt::decrypt($connection->password) : env('DROCERCA_FTP_PASS'));

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de Drocerca (usuario o contraseña vacíos).");
        }

        $clientCode = $user;
        if (!empty($connection?->invoice_path)) {
            $parts = explode('/', trim($connection->invoice_path, '/'));
            $lastPart = end($parts);
            if (!empty($lastPart)) {
                $clientCode = $lastPart;
            }
        }

        // Carpeta remota según manual oficial: 'pedidos' en la raíz
        $remoteDir = 'pedidos';

        // Conectar al servidor FTP
        $ftp = @ftp_connect($host, $port, 15);
        if ($ftp === false) {
            Log::error("[DROCERCA FTP] Fallo ftp_connect a {$host}:{$port}");
            throw new Exception("No se pudo conectar al servidor FTP de Drocerca ({$host}:{$port})");
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            @ftp_close($ftp);
            $sslFtp = @ftp_ssl_connect($host, $port, 15);
            if ($sslFtp !== false && @ftp_login($sslFtp, $user, $pass)) {
                $ftp = $sslFtp;
                $login = true;
            } else {
                if ($sslFtp !== false) {
                    @ftp_close($sslFtp);
                }
                throw new Exception("Error de autenticación FTP en Drocerca para el usuario '{$user}'.");
            }
        }

        ftp_pasv($ftp, (bool) ($connection?->pasv ?? true));

        // Verificar o crear carpeta pedidos si no existe
        $fileList = @ftp_nlist($ftp, '.');
        if ($fileList !== false) {
            $hasPedidosDir = false;
            foreach ($fileList as $f) {
                if (strtolower(basename($f)) === 'pedidos') {
                    $hasPedidosDir = true;
                    break;
                }
            }
            if (!$hasPedidosDir) {
                @ftp_mkdir($ftp, 'pedidos');
            }
        }

        // Nombre de archivo según manual: código de cliente + número de pedido + timestamp
        $timestamp = date('YmdHis');
        $fileName = "pedido_{$clientCode}_{$autoOrder->id}_{$timestamp}.txt";
        $remoteFilePath = rtrim($remoteDir, '/') . '/' . $fileName;

        // Generar contenido del archivo plano delimitado por punto y coma
        $content = $this->generateOrderContent($autoOrder, $clientCode, $sede);

        // Guardar temporalmente y subir
        $tempPath = tempnam(sys_get_temp_dir(), 'drocerca_order_');
        file_put_contents($tempPath, $content);

        $uploadSuccess = @ftp_put($ftp, $remoteFilePath, $tempPath, FTP_BINARY);
        @unlink($tempPath);
        @ftp_close($ftp);

        if (!$uploadSuccess) {
            $lastError = error_get_last();
            Log::error("[DROCERCA FTP] Error subiendo {$remoteFilePath}", ['error' => $lastError['message'] ?? 'Desconocido']);
            throw new Exception("No se pudo transferir el archivo de pedido {$fileName} a la carpeta 'pedidos' del FTP de Drocerca.");
        }

        Log::info("[DROCERCA FTP] Pedido #{$autoOrder->id} subido con éxito a {$remoteFilePath}");

        return [
            'success' => true,
            'filename' => $fileName,
            'remote_path' => $remoteFilePath,
            'message' => "Pedido transmitido con éxito a Drocerca ({$fileName}).",
        ];
    }

    /**
     * Descarga y parsea el archivo de inventario consolidado 'inventario.txt' desde la raíz del FTP.
     */
    public function fetchInventoryFtp(?string $host = null, ?string $user = null, ?string $password = null): array
    {
        $ftpHost = $host ?: env('DROCERCA_FTP_HOST', 'drocerca.proteoerp.org');
        $ftpUser = $user ?: env('DROCERCA_FTP_USER', 'W008B3');
        $ftpPass = $password ?: env('DROCERCA_FTP_PASS', 'J505406957');

        $ftp = @ftp_connect($ftpHost, 21, 20);
        if (!$ftp) {
            throw new Exception("No se pudo conectar al FTP de Drocerca para descargar inventario.txt");
        }

        if (!@ftp_login($ftp, $ftpUser, $ftpPass)) {
            @ftp_close($ftp);
            throw new Exception("Credenciales FTP inválidas para descargar inventario Drocerca.");
        }

        ftp_pasv($ftp, true);

        $tempFile = tempnam(sys_get_temp_dir(), 'drocerca_inv_');
        $downloadSuccess = @ftp_get($ftp, $tempFile, 'inventario.txt', FTP_BINARY);
        @ftp_close($ftp);

        if (!$downloadSuccess || !file_exists($tempFile)) {
            @unlink($tempFile);
            throw new Exception("No se encontró el archivo 'inventario.txt' en la raíz del FTP de Drocerca.");
        }

        $lines = file($tempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        @unlink($tempFile);

        $products = [];
        foreach ($lines as $line) {
            $cols = explode(';', $line);
            if (count($cols) < 8) {
                continue;
            }

            // Estructura oficial según manual de inventario:
            // 0: Codigo, 1: CodBarras, 2: Descripcion, 3: exis Merida, 4: exisCaracas, 5: exisOriente,
            // 6: Marca, 7: Precio, 8: Oferta, 9: Grupo, 10: Vence, 11: Principio, 12: Escala, 13: DescXEscala, 14: Bonifica, 15: CantBonifica
            $products[] = [
                'codigo' => trim($cols[0]),
                'barcode' => trim($cols[1] ?? ''),
                'descripcion' => trim($cols[2] ?? ''),
                'existencia_merida' => (float) str_replace(',', '.', trim($cols[3] ?? '0')),
                'existencia_centro' => (float) str_replace(',', '.', trim($cols[4] ?? '0')),
                'existencia_oriente' => (float) str_replace(',', '.', trim($cols[5] ?? '0')),
                'marca' => trim($cols[6] ?? ''),
                'precio' => (float) str_replace(',', '.', trim($cols[7] ?? '0')),
                'precio_oferta' => isset($cols[8]) ? (float) str_replace(',', '.', trim($cols[8])) : null,
                'categoria' => trim($cols[9] ?? ''),
                'fecha_vencimiento' => trim($cols[10] ?? ''),
                'principio_activo' => trim($cols[11] ?? ''),
            ];
        }

        return $products;
    }
}
