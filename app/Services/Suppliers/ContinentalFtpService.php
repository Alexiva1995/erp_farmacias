<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\ContinentalFtpServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Exception;
use Illuminate\Support\Facades\Log;

class ContinentalFtpService implements ContinentalFtpServiceInterface
{
    /**
     * Genera el contenido plano del pedido según el Manual de Integración FTP (Sección 5):
     * Separador de campos: ;
     * Separador decimal: .
     * Fin de línea: Windows (CR+LF)
     * Codificación: ISO-8859-1 (Latin-1)
     * Estructura por línea:
     * 1. Código del artículo en la droguería (5 dígitos con ceros a la izquierda)
     * 2. Descripción (hasta 40 caracteres)
     * 3. Cantidad (entero)
     * 4. Precio total (2 decimales con punto)
     */
    public function generateOrderContent(AutoOrder $autoOrder): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];

        foreach ($autoOrder->details as $detail) {
            // 1. Código del artículo en la droguería (campo 1 del inventario, 5 dígitos con ceros a la izquierda)
            $code = $detail->productSupplier?->cod_supplier
                ?? $detail->product?->barcode
                ?? (string) $detail->product_id;
            $code = trim((string) $code);
            if (is_numeric($code) && strlen($code) < 5) {
                $code = str_pad($code, 5, '0', STR_PAD_LEFT);
            }

            // 2. Descripción del producto (hasta 40 caracteres, sin punto y coma)
            $name = $detail->productSupplier?->name
                ?? $detail->product?->name
                ?? 'PRODUCTO';
            $name = mb_substr(trim((string) $name), 0, 40);
            $name = str_replace(';', ',', $name);

            // 3. Cantidad solicitada (entero)
            $quantity = (int) $detail->quantity;

            // 4. Precio total de la línea (informativo, 2 decimales con punto)
            $unitCost = (float) ($detail->unit_cost
                ?? $detail->productSupplier?->unit_cost_usd_with_discount
                ?? $detail->productSupplier?->unit_cost_usd
                ?? $detail->productSupplier?->unit_cost
                ?? 0);
            $totalPrice = number_format($quantity * $unitCost, 2, '.', '');

            $lines[] = "{$code};{$name};{$quantity};{$totalPrice}";
        }

        $plain = implode("\r\n", $lines) . "\r\n";

        // Codificación ISO-8859-1 (Latin-1) según manual
        return mb_convert_encoding($plain, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Transmite el pedido automático al servidor FTP de Droguería Intercontinental en la carpeta 'Pedidos'.
     * Nombre del archivo: código de cliente + P + número de pedido + .txt (ej. 3469P00012.txt)
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

        // Buscar conexión FTP configurada para Continental (ID 1032 o por host/nombre)
        $connection = $supplier->connections()
            ->where('type', 'ftp')
            ->first();

        if (!$connection) {
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where(function ($query) {
                    $query->where('host', 'LIKE', '%186.167.68.54%')
                        ->orWhere('host', 'LIKE', '%contin%')
                        ->orWhere('username', 'LIKE', '%contin%');
                })
                ->first();
        }

        $host = $connection?->host ?: '186.167.68.54';
        $host = preg_replace('#^(https?|ftps?)://#i', '', trim($host));
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        $port = (int) ($connection?->port ?? 21);
        $user = (string) ($connection?->username ?? '');
        $pass = (string) ($connection?->password ? FtpCrypt::decrypt($connection->password) : '');

        if (trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de Droguería Intercontinental (usuario o contraseña vacíos).");
        }

        // Obtener código de cliente a partir del usuario (según manual: 'C0' seguido del código, ej. C03469 -> 3469)
        $clientCode = preg_replace('/^C0*/i', '', trim($user));
        if (empty($clientCode)) {
            $clientCode = trim($user);
        }

        // Número de pedido con ceros a la izquierda (mínimo 5 dígitos según ejemplo 3469P00012.txt)
        $orderNumber = str_pad((string) $autoOrder->id, 5, '0', STR_PAD_LEFT);
        $fileName = "{$clientCode}P{$orderNumber}.txt";
        $remoteDir = 'Pedidos';
        $remoteFilePath = "{$remoteDir}/{$fileName}";

        // Conectar al servidor FTP (protocolo plano sin cifrado según manual)
        $ftp = @ftp_connect($host, $port, 20);
        if ($ftp === false) {
            Log::error("[CONTINENTAL FTP] Fallo ftp_connect a {$host}:{$port}");
            throw new Exception("No se pudo conectar al servidor FTP de Droguería Intercontinental ({$host}:{$port})");
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            @ftp_close($ftp);
            throw new Exception("Error de autenticación FTP en Droguería Intercontinental para el usuario '{$user}'.");
        }

        // Modo pasivo obligatorio según sección 1 del manual
        ftp_pasv($ftp, true);

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

        // Guardar temporalmente y transferir por FTP
        $tempPath = tempnam(sys_get_temp_dir(), 'contin_order_');
        file_put_contents($tempPath, $content);

        $uploadSuccess = @ftp_put($ftp, $remoteFilePath, $tempPath, FTP_BINARY);
        @unlink($tempPath);
        @ftp_close($ftp);

        if (!$uploadSuccess) {
            $lastError = error_get_last();
            Log::error("[CONTINENTAL FTP] Error subiendo {$remoteFilePath}", ['error' => $lastError['message'] ?? 'Desconocido']);
            throw new Exception("No se pudo transferir el archivo de pedido {$fileName} a la carpeta 'Pedidos' del FTP de Droguería Intercontinental.");
        }

        Log::info("[CONTINENTAL FTP] Pedido #{$autoOrder->id} transmitido con éxito a {$remoteFilePath}");

        return [
            'success' => true,
            'filename' => $fileName,
            'remote_path' => $remoteFilePath,
            'message' => "Pedido transmitido con éxito a Droguería Intercontinental ({$fileName}).",
        ];
    }
}
