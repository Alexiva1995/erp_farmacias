<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\VitalclinicFtpServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Exception;
use Illuminate\Support\Facades\Log;

class VitalclinicFtpService implements VitalclinicFtpServiceInterface
{
    /**
     * Genera el contenido del archivo de pedido .txt sin encabezado según el protocolo de Vitalclinic:
     * Separador de campos: ;
     * Separador de decimales: . (2 decimales)
     * Estructura:
     * codigo_producto (cadena(6)) ; descripcion_producto (cadena) ; Cantidad (entero) ; precio_unitario (decimal)
     */
    public function generateOrderContent(AutoOrder $autoOrder): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];

        foreach ($autoOrder->details as $detail) {
            // Código de producto asignado por Vitalclinic
            $code = $detail->productSupplier?->cod_supplier 
                ?? $detail->product?->barcode 
                ?? (string) $detail->product_id;
            $code = trim((string) $code);

            // Descripción del producto
            $name = $detail->productSupplier?->name 
                ?? $detail->product?->name 
                ?? 'PRODUCTO SIN NOMBRE';
            $name = trim((string) $name);

            // Cantidad entera
            $quantity = (int) $detail->quantity;

            // Precio unitario con punto y 2 decimales
            $unitCost = number_format((float) ($detail->unit_cost ?? 0), 2, '.', '');

            $lines[] = "{$code};{$name};{$quantity};{$unitCost}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Conecta al servidor FTP de Vitalclinic y sube el archivo a la carpeta 'Pedidos'.
     * Nombre: código de cliente(4) + P + correlativo(6).txt (ej. 3613P000001.txt)
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

        // Buscar conexión FTP de Vitalclinic
        $connection = $supplier->connections()
            ->where('type', 'ftp')
            ->first();

        if (!$connection) {
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where(function ($query) {
                    $query->where('host', 'LIKE', '%vitalclinic%')
                        ->orWhere('username', 'LIKE', '%vitalclinic%');
                })
                ->first();
        }

        if (!$connection) {
            throw new Exception("No se encontró una conexión FTP configurada para el proveedor {$supplier->name}");
        }

        $host = $connection->host ?: '195.35.33.28';
        $port = (int) ($connection->port ?? 21);
        $user = (string) ($connection->username ?? '');
        $pass = (string) FtpCrypt::decrypt($connection->password ?? '');

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de Vitalclinic (usuario o contraseña vacíos).");
        }

        // Extraer código de cliente (ej: de username o invoice_path)
        $clientCode = $user ?: '3613';
        if (!empty($connection->invoice_path)) {
            $parts = explode('/', trim($connection->invoice_path, '/'));
            $lastPart = end($parts);
            if (!empty($lastPart) && is_numeric($lastPart)) {
                $clientCode = str_pad($lastPart, 4, '0', STR_PAD_LEFT);
            }
        }

        // Directorio de destino según protocolo
        $remoteDir = 'Pedidos';

        // Conectar al FTP
        $ftp = @ftp_connect($host, $port, 15);
        if ($ftp === false) {
            Log::error("[VITALCLINIC FTP] Fallo ftp_connect a {$host}:{$port}");
            throw new Exception("No se pudo conectar al servidor FTP de Vitalclinic ({$host})");
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
                throw new Exception("Error de autenticación FTP en Vitalclinic para el usuario '{$user}'.");
            }
        }

        ftp_pasv($ftp, (bool) ($connection->pasv ?? true));

        // Listar archivos en Pedidos para determinar el correlativo
        $fileList = @ftp_nlist($ftp, $remoteDir);
        if ($fileList === false) {
            ftp_pasv($ftp, !($connection->pasv ?? true));
            $fileList = @ftp_nlist($ftp, $remoteDir) ?: [];
        }

        $nextNumber = 1;
        $existingOrders = [];

        // Buscar patrón {clientCode}P(\d+)\.txt
        $pattern = '/^' . preg_quote($clientCode, '/') . 'P(\d+)\.txt$/i';
        foreach ($fileList as $file) {
            $base = basename($file);
            if (preg_match($pattern, $base, $matches)) {
                $existingOrders[] = (int) $matches[1];
            }
        }

        if (!empty($existingOrders)) {
            $nextNumber = max($existingOrders) + 1;
        }

        // Formato: código de cliente(4) + P + correlativo de 6 dígitos (ej. 3613P000001.txt)
        $fileName = sprintf('%sP%06d.txt', $clientCode, $nextNumber);
        $remoteFilePath = rtrim($remoteDir, '/') . '/' . $fileName;

        // Generar contenido plano
        $content = $this->generateOrderContent($autoOrder);

        // Crear archivo temporal
        $tempPath = tempnam(sys_get_temp_dir(), 'vital_order_');
        file_put_contents($tempPath, $content);

        // Subir vía FTP en modo BINARY
        $uploadSuccess = @ftp_put($ftp, $remoteFilePath, $tempPath, FTP_BINARY);
        @unlink($tempPath);
        @ftp_close($ftp);

        if (!$uploadSuccess) {
            $lastError = error_get_last();
            Log::error("[VITALCLINIC FTP] Error subiendo {$remoteFilePath}", ['error' => $lastError['message'] ?? 'Desconocido']);
            throw new Exception("No se pudo transferir el archivo de pedido {$fileName} al FTP de Vitalclinic.");
        }

        Log::info("[VITALCLINIC FTP] Pedido #{$autoOrder->id} enviado exitosamente a {$remoteFilePath}");

        return [
            'success' => true,
            'filename' => $fileName,
            'remote_path' => $remoteFilePath,
            'message' => "Pedido transmitido con éxito a Droguería Vitalclinic ({$fileName}).",
        ];
    }
}
