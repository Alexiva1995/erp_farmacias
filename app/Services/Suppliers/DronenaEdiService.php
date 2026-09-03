<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DronenaEdiServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Exception;
use Illuminate\Support\Facades\Log;

class DronenaEdiService implements DronenaEdiServiceInterface
{
    /**
     * Genera el archivo plano de pedido con formato de longitud fija según especificaciones de Droguería Nena.
     *
     * Estructura:
     * D000 <order_id_6_digits>
     * D001 <cod_supplier>
     * D002 <quantity>
     * D003 <name>
     * Separador de línea: \r\n (CR+LF)
     */
    public function generateOrderContent(AutoOrder $autoOrder): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];

        // Encabezado: Identificador D000 + Espacio + Número de Pedido (formateado a 6 dígitos o caracteres)
        $orderNumber = str_pad((string) $autoOrder->id, 6, '0', STR_PAD_LEFT);
        if (strlen($orderNumber) > 6) {
            $orderNumber = substr($orderNumber, -6);
        }
        $lines[] = "D000 {$orderNumber}";

        foreach ($autoOrder->details as $detail) {
            // Obtener código de producto de Droguería Nena
            $code = $detail->productSupplier?->cod_supplier 
                ?? $detail->product?->barcode 
                ?? (string) $detail->product_id;
            $code = trim((string) $code);

            // Cantidad
            $quantity = (int) $detail->quantity;

            // Descripción (máximo 51 caracteres)
            $name = $detail->productSupplier?->name 
                ?? $detail->product?->name 
                ?? 'PRODUCTO SIN NOMBRE';
            $name = mb_substr(trim((string) $name), 0, 51, 'UTF-8');

            $lines[] = "D001 {$code}";
            $lines[] = "D002 {$quantity}";
            $lines[] = "D003 {$name}";
        }

        // Fin de línea CR+LF obligatorio por especificación
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Conecta al servidor FTP de Dronena y sube el archivo FACTUXX al directorio del cliente.
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

        // Buscar conexión FTP de Dronena
        $connection = $supplier->connections()
            ->where('type', 'ftp')
            ->first();

        if (!$connection) {
            // Intentar por host si el tipo fue registrado genérico
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where('host', 'LIKE', '%dronena%')
                ->first();
        }

        if (!$connection) {
            throw new Exception("No se encontró una conexión FTP configurada para el proveedor {$supplier->name}");
        }

        $host = $connection->host ?: 'ftp.dronena.com';
        $port = (int) ($connection->port ?? 21);
        $user = (string) ($connection->username ?? '');
        $pass = (string) FtpCrypt::decrypt($connection->password ?? '');

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de Droguería Nena (usuario o contraseña vacíos).");
        }

        // Determinar carpeta de destino
        // Si en la conexión el path es Clientes/d719/Inventario.txt, extraemos la carpeta base del cliente: Clientes/d719
        $remoteDir = 'Clientes/' . strtolower(explode('-', $user)[0]);
        if (!empty($connection->path)) {
            $dirName = dirname($connection->path);
            if ($dirName !== '.' && !empty($dirName)) {
                $remoteDir = $dirName;
            }
        }

        // Conectar al FTP
        $ftp = @ftp_connect($host, $port, 15);
        if ($ftp === false) {
            Log::error("[DRONENA EDI] Fallo ftp_connect a {$host}:{$port}");
            throw new Exception("No se pudo conectar al servidor FTP de Droguería Nena ({$host})");
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            // Intentar SSL en caso de requerirlo
            @ftp_close($ftp);
            $sslFtp = @ftp_ssl_connect($host, $port, 15);
            if ($sslFtp !== false && @ftp_login($sslFtp, $user, $pass)) {
                $ftp = $sslFtp;
                $login = true;
            } else {
                if ($sslFtp !== false) {
                    @ftp_close($sslFtp);
                }
                throw new Exception("Error de autenticación FTP en Droguería Nena para el usuario '{$user}'.");
            }
        }

        ftp_pasv($ftp, (bool) ($connection->pasv ?? true));

        // Listar archivos en la carpeta remota para calcular correlativo FACTUXX
        $fileList = @ftp_nlist($ftp, $remoteDir);
        if ($fileList === false) {
            // Si falla con pasv actual, intentar invertir pasv
            ftp_pasv($ftp, !($connection->pasv ?? true));
            $fileList = @ftp_nlist($ftp, $remoteDir) ?: [];
        }

        $nextNumber = 1;
        $existingFactu = [];

        foreach ($fileList as $file) {
            $base = basename($file);
            if (preg_match('/^FACTU(\d+)(\.txt)?$/i', $base, $matches)) {
                $existingFactu[] = (int) $matches[1];
            }
        }

        if (!empty($existingFactu)) {
            $nextNumber = max($existingFactu) + 1;
        }

        // Formato FACTUXX.txt con 2 dígitos mínimo (ej. FACTU01.txt, FACTU02.txt)
        $fileName = sprintf('FACTU%02d.txt', $nextNumber);
        $remoteFilePath = rtrim($remoteDir, '/') . '/' . $fileName;

        // Generar contenido plano
        $content = $this->generateOrderContent($autoOrder);

        // Crear archivo temporal
        $tempPath = tempnam(sys_get_temp_dir(), 'edi_factu_');
        file_put_contents($tempPath, $content);

        // Subir vía FTP en modo BINARY
        $uploadSuccess = @ftp_put($ftp, $remoteFilePath, $tempPath, FTP_BINARY);
        @unlink($tempPath);
        @ftp_close($ftp);

        if (!$uploadSuccess) {
            $lastError = error_get_last();
            Log::error("[DRONENA EDI] Error subiendo {$remoteFilePath}", ['error' => $lastError['message'] ?? 'Desconocido']);
            throw new Exception("No se pudo transferir el archivo de pedido {$fileName} al FTP de Droguería Nena.");
        }

        Log::info("[DRONENA EDI] Pedido #{$autoOrder->id} enviado exitosamente a {$remoteFilePath}");

        return [
            'success' => true,
            'filename' => $fileName,
            'remote_path' => $remoteFilePath,
            'message' => "Pedido transmitido con éxito a Droguería Nena ({$fileName}).",
        ];
    }
}
