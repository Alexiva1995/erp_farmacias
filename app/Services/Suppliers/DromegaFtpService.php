<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DromegaFtpServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Exception;
use Illuminate\Support\Facades\Log;

class DromegaFtpService implements DromegaFtpServiceInterface
{
    /**
     * Genera el contenido plano del pedido en formato TXT según especificaciones de DROMEGA:
     * Sin encabezado, separador ';', separador decimal '.', 2 decimales.
     * Estructura por línea:
     * 1. codigo_producto (cadena(6))
     * 2. descripcion_producto (cadena)
     * 3. Cantidad (entero)
     * 4. precio_unitario (decimal con 2 decimales)
     */
    public function generateOrderContent(AutoOrder $autoOrder): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];

        foreach ($autoOrder->details as $detail) {
            // 1. Código interno del producto asignado por Dromega (máximo 6 caracteres)
            $code = $detail->productSupplier?->cod_supplier 
                ?? $detail->product?->barcode 
                ?? (string) $detail->product_id;
            $code = mb_substr(trim((string) $code), 0, 6);

            // 2. Descripción del producto
            $name = $detail->productSupplier?->name 
                ?? $detail->product?->name 
                ?? 'PRODUCTO';
            $name = trim((string) $name);

            // 3. Cantidad solicitada (entero)
            $quantity = (int) $detail->quantity;

            // 4. Precio unitario (decimal con 2 decimales y punto)
            $price = number_format((float) ($detail->unit_cost ?? 0), 2, '.', '');

            $lines[] = "{$code};{$name};{$quantity};{$price}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Transmite el pedido automático al servidor FTP de DROMEGA en la carpeta 'Pedidos'.
     * Nombre del archivo: código de cliente(caracter(4)) + P + correlativo de pedido(carácter(6)).txt
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array
    {
        $supplier = $autoOrder->supplier;
        if (!$supplier) {
            $supplier = Supplier::with('connections')->find($autoOrder->supplier_id);
        }

        if (!$supplier) {
            throw new Exception("No se encontró el proveedor asociado a la orden #{$autoOrder->id}");
        }

        // Buscar conexión FTP configurada para Dromega
        $connection = $supplier->connections()
            ->where('type', 'ftp')
            ->first();

        if (!$connection) {
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where(function ($query) {
                    $query->where('host', 'LIKE', '%dromega%')
                        ->orWhere('username', 'LIKE', '%dromega%');
                })
                ->first();
        }

        $host = $connection?->host ?: env('DROMEGA_FTP_HOST', 'ftp.dromega.com.ve');
        // Limpiar protocolo y barras si fueron ingresadas en el host
        $host = preg_replace('#^(https?|ftps?)://#i', '', trim($host));
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        $port = (int) ($connection?->port ?? env('DROMEGA_FTP_PORT', 21));
        $user = (string) ($connection?->username ?? env('DROMEGA_FTP_USER', 'clientemerida@dromega.com.ve'));
        $pass = (string) ($connection?->password ? FtpCrypt::decrypt($connection->password) : env('DROMEGA_FTP_PASS', 'Bgw9Uo+ya_j'));

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de DROMEGA (usuario o contraseña vacíos).");
        }

        // Extraer código de cliente de 4 dígitos (ej. 7586)
        $clientCode = '7586';
        if (!empty($connection?->invoice_path)) {
            $parts = explode('/', trim($connection->invoice_path, '/'));
            $lastPart = end($parts);
            if (!empty($lastPart) && is_numeric($lastPart)) {
                $clientCode = $lastPart;
            }
        }
        $clientCode = str_pad(substr((string) $clientCode, 0, 4), 4, '0', STR_PAD_LEFT);

        // Correlativo de pedido por cliente (6 dígitos)
        $correlative = str_pad((string) ($autoOrder->id % 1000000), 6, '0', STR_PAD_LEFT);
        $fileName = "{$clientCode}P{$correlative}.txt";
        $remoteDir = 'Pedidos';
        $remoteFilePath = "{$remoteDir}/{$fileName}";

        // Conectar al servidor FTP
        $ftp = @ftp_connect($host, $port, 15);
        if ($ftp === false) {
            Log::error("[DROMEGA FTP] Fallo ftp_connect a {$host}:{$port}");
            throw new Exception("No se pudo conectar al servidor FTP de DROMEGA ({$host}:{$port})");
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
                throw new Exception("Error de autenticación FTP en DROMEGA para el usuario '{$user}'.");
            }
        }

        ftp_pasv($ftp, (bool) ($connection?->pasv ?? true));

        // Verificar o crear carpeta Pedidos si no existe
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
                @ftp_mkdir($ftp, 'Pedidos');
            }
        }

        // Generar contenido del pedido
        $content = $this->generateOrderContent($autoOrder);

        // Guardar temporalmente y subir
        $tempPath = tempnam(sys_get_temp_dir(), 'dromega_order_');
        file_put_contents($tempPath, $content);

        $uploadSuccess = @ftp_put($ftp, $remoteFilePath, $tempPath, FTP_BINARY);
        @unlink($tempPath);
        @ftp_close($ftp);

        if (!$uploadSuccess) {
            $lastError = error_get_last();
            Log::error("[DROMEGA FTP] Error subiendo {$remoteFilePath}", ['error' => $lastError['message'] ?? 'Desconocido']);
            throw new Exception("No se pudo transferir el archivo de pedido {$fileName} a la carpeta 'Pedidos' del FTP de DROMEGA.");
        }

        Log::info("[DROMEGA FTP] Pedido #{$autoOrder->id} transmitido con éxito a {$remoteFilePath}");

        return [
            'success' => true,
            'filename' => $fileName,
            'remote_path' => $remoteFilePath,
            'message' => "Pedido transmitido con éxito a DROMEGA ({$fileName}).",
        ];
    }

    /**
     * Descarga y parsea el archivo de inventario consolidado 'inventariomerida.txt'.
     */
    public function fetchInventoryFtp(?string $host = null, ?string $user = null, ?string $password = null): array
    {
        $ftpHost = $host ?: env('DROMEGA_FTP_HOST', 'ftp.dromega.com.ve');
        $ftpHost = preg_replace('#^(https?|ftps?)://#i', '', trim($ftpHost));
        $ftpHost = explode('/', $ftpHost)[0];
        $ftpHost = explode(':', $ftpHost)[0];

        $ftpUser = $user ?: env('DROMEGA_FTP_USER', 'clientemerida@dromega.com.ve');
        $ftpPass = $password ?: env('DROMEGA_FTP_PASS', 'Bgw9Uo+ya_j');

        $ftp = @ftp_connect($ftpHost, 21, 20);
        if (!$ftp) {
            throw new Exception("No se pudo conectar al FTP de DROMEGA para descargar inventariomerida.txt");
        }

        if (!@ftp_login($ftp, $ftpUser, $ftpPass)) {
            @ftp_close($ftp);
            throw new Exception("Credenciales FTP inválidas para descargar inventario DROMEGA.");
        }

        ftp_pasv($ftp, true);

        $tempFile = tempnam(sys_get_temp_dir(), 'dromega_inv_');
        $remotePath = 'Existencia/inventariomerida.txt';
        $downloadSuccess = @ftp_get($ftp, $tempFile, $remotePath, FTP_BINARY);

        if (!$downloadSuccess) {
            // Intentar desde la raíz si no se encuentra en Existencia/
            $downloadSuccess = @ftp_get($ftp, $tempFile, 'inventariomerida.txt', FTP_BINARY);
        }

        @ftp_close($ftp);

        if (!$downloadSuccess || !file_exists($tempFile)) {
            @unlink($tempFile);
            throw new Exception("No se encontró el archivo 'inventariomerida.txt' en el FTP de DROMEGA.");
        }

        $lines = file($tempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        @unlink($tempFile);

        $products = [];
        foreach ($lines as $line) {
            $cols = explode(';', $line);
            if (count($cols) < 8) {
                continue;
            }

            // Estructura oficial Dromega:
            // 0: codigo_producto, 1: codigo_barras, 2: descripcion_producto, 3: fecha_lote (DD/MM/YYYY),
            // 4: precio_unitario, 5: porcentaje_oferta_viegente, 6: precio_unitario_final, 7: stock_disponible
            $products[] = [
                'codigo' => trim($cols[0]),
                'barcode' => trim($cols[1] ?? ''),
                'descripcion' => trim($cols[2] ?? ''),
                'fecha_lote' => trim($cols[3] ?? ''),
                'precio_unitario' => (float) str_replace(',', '.', trim($cols[4] ?? '0')),
                'porcentaje_oferta' => (float) str_replace(',', '.', trim($cols[5] ?? '0')),
                'precio_final' => (float) str_replace(',', '.', trim($cols[6] ?? '0')),
                'stock' => (int) trim($cols[7] ?? '0'),
            ];
        }

        return $products;
    }
}
