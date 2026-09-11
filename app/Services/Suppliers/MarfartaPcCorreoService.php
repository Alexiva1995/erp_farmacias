<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\MarfartaPcCorreoServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarfartaPcCorreoService implements MarfartaPcCorreoServiceInterface
{
    /**
     * Genera el nombre de archivo normalizado según el protocolo PC-CORREO:
     * Nombre del archivo: PC{CodProveedor(3)}{CodCliente(7)}{NumODC(10)}.txt
     * Ejemplo: PC00300313730000000012.txt
     */
    public function generateFilename(AutoOrder $autoOrder, ?string $clientCode = null, ?string $supplierCode = '003'): string
    {
        $supplierCodeStr = str_pad(trim((string)($supplierCode ?: '003')), 3, '0', STR_PAD_LEFT);
        $clientCodeClean = preg_replace('/\D/', '', (string)($clientCode ?: '31373'));
        $clientCodeStr = str_pad($clientCodeClean, 7, '0', STR_PAD_LEFT);
        $odcNumber = str_pad((string)($autoOrder->id ?? 1), 10, '0', STR_PAD_LEFT);

        return "PC{$supplierCodeStr}{$clientCodeStr}{$odcNumber}.txt";
    }

    /**
     * Genera el contenido del archivo de pedido .txt según el protocolo PC-CORREO:
     * Sintaxis:
     * TipoRegistro (1 pos: T/D) + NúmeroCampo (3 pos) + Contenido (hasta 1000 pos)
     *
     * Encabezado del Pedido (T):
     * T001: Número de la ODC.
     * T002: Código del Cliente en el Proveedor.
     * T003: Código del Proveedor.
     * T004: Fecha de la ODC (DD/MM/AAAA).
     * T005: Hora de la ODC (HH:MM).
     * T006: Cantidad de renglones en la ODC.
     *
     * Detalle del Pedido (D):
     * D001: Código Interno del Artículo en Proveedor.
     * D002: Cantidad pedida.
     * D101: Código del artículo en la farmacia.
     * D102: Código de Industria / Código de barra.
     * D105: Identificador Rx u OTC.
     */
    public function generateOrderContent(AutoOrder $autoOrder, ?string $clientCode = null, ?string $supplierCode = '003'): string
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $lines = [];
        $supplierCodeStr = trim((string)($supplierCode ?: '003'));
        $clientCodeClean = preg_replace('/\D/', '', (string)($clientCode ?: '31373'));
        $odcNumber = (string)($autoOrder->id ?? 1);
        $createdDate = $autoOrder->created_at ? Carbon::parse($autoOrder->created_at) : Carbon::now();
        $dateStr = $createdDate->format('d/m/Y');
        $timeStr = $createdDate->format('H:i');
        $totalLines = count($autoOrder->details);

        // Encabezado del Pedido (T)
        $lines[] = "T001{$odcNumber}";
        $lines[] = "T002{$clientCodeClean}";
        $lines[] = "T003{$supplierCodeStr}";
        $lines[] = "T004{$dateStr}";
        $lines[] = "T005{$timeStr}";
        $lines[] = "T006{$totalLines}";

        // Detalle del Pedido (D)
        foreach ($autoOrder->details as $detail) {
            // D001: Código Interno del Artículo en el Proveedor (Mafarta/Cobeca)
            $supplierItemCode = $detail->productSupplier?->cod_supplier;
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                if (!empty($detail->product_suppliers_id)) {
                    $psDirect = \App\Models\ProductSupplier::find($detail->product_suppliers_id);
                    $codeDirect = $psDirect?->cod_supplier;
                    if (!empty($codeDirect) && $codeDirect !== '0' && $codeDirect !== 0) {
                        $supplierItemCode = $codeDirect;
                    }
                }
            }

            // Si sigue en 0 o vacío, buscar en el catálogo de Mafarta/Cobeca por código de barras
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                $barcode = $detail->product?->barcode;
                if (!empty($barcode)) {
                    $psMafarta = \App\Models\ProductSupplier::where('supplier_id', $autoOrder->supplier_id)
                        ->where(function ($q) use ($barcode) {
                            $q->where('barcode_match', $barcode);
                        })
                        ->whereNotNull('cod_supplier')
                        ->where('cod_supplier', '!=', '')
                        ->where('cod_supplier', '!=', '0')
                        ->first();
                    if ($psMafarta) {
                        $supplierItemCode = $psMafarta->cod_supplier;
                    }
                }
            }

            // Si no tiene código interno de Mafarta, usar el código de barras o ID de producto como último recurso
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                $supplierItemCode = $detail->productSupplier?->barcode_match 
                    ?? $detail->product?->barcode 
                    ?? (string) $detail->product_id;
            }
            $supplierItemCode = trim((string) $supplierItemCode);

            // D002: Cantidad pedida (entero)
            $qty = (int) $detail->quantity;

            // D101: Código de farmacia
            $pharmacyCode = (string) ($detail->product_id ?? $supplierItemCode);

            // D102: Código de barra / industria
            $industryCode = trim((string) ($detail->product?->barcode ?? $supplierItemCode));

            // D105: Clasificación Rx / OTC
            $isPrescription = !empty($detail->product?->requires_prescription) || !empty($detail->product?->is_prescription);
            $rxOtc = $isPrescription ? 'RX' : 'OTC';

            $lines[] = "D001{$supplierItemCode}";
            $lines[] = "D002{$qty}";
            $lines[] = "D101{$pharmacyCode}";
            $lines[] = "D102{$industryCode}";
            $lines[] = "D105{$rxOtc}";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Conecta al servidor FTP / SFTP de Mafarta y sube el archivo de pedido PC-CORREO.
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

        // 1. Buscar conexión específica de FTP/SFTP para Mafarta/Cobeca
        $connection = $supplier->connections()
            ->whereIn('type', ['ftp', 'sftp'])
            ->first();

        if (!$connection) {
            $connection = SupplierConnection::where('supplier_id', $supplier->id)
                ->where(function ($query) {
                    $query->where('host', 'LIKE', '%mafarta%')
                        ->orWhere('host', 'LIKE', '%cobeca%')
                        ->orWhere('username', 'LIKE', '%mafarta%')
                        ->orWhere('username', 'LIKE', '%cobeca%');
                })
                ->first();
        }

        $host = $connection?->host ?: env('MAFARTA_FTP_HOST');
        $port = (int) ($connection?->port ?? env('MAFARTA_FTP_PORT', 21));
        $user = (string) ($connection?->username ?? env('MAFARTA_FTP_USER'));
        $pass = (string) ($connection?->password ? FtpCrypt::decrypt($connection->password) : env('MAFARTA_FTP_PASS'));

        if (!$host || trim($user) === '' || trim($pass) === '') {
            throw new Exception("Faltan las credenciales FTP de Mafarta para la transmisión de pedidos (Host, Usuario o Contraseña no configurados). Por favor configure la conexión FTP del proveedor.");
        }

        $clientCode = env('MAFARTA_CLIENTE', '31373');
        if (!empty($connection?->username) && is_numeric(preg_replace('/\D/', '', $connection->username))) {
            $clientCode = preg_replace('/\D/', '', $connection->username);
        }

        $supplierCode = env('MAFARTA_COD_DROGUERIA', '003');
        $filename = $this->generateFilename($autoOrder, (string)$clientCode, (string)$supplierCode);
        $content = $this->generateOrderContent($autoOrder, (string)$clientCode, (string)$supplierCode);

        // Directorio remoto (por defecto raíz o subcarpeta si se especifica en la conexión)
        $remoteDir = trim((string)($connection?->path ?? ''));

        // Guardar archivo temporal local
        $tempFile = tempnam(sys_get_temp_dir(), 'mafarta_odc_');
        file_put_contents($tempFile, $content);

        // Intentar conexión FTP / FTPS
        $ftp = @ftp_connect($host, $port, 20);
        $isSsl = false;

        if ($ftp === false) {
            try {
                $ftp = @ftp_ssl_connect($host, $port, 20);
                if ($ftp !== false) {
                    $isSsl = true;
                }
            } catch (\Throwable $e) {
                Log::warning("[MAFARTA PC-CORREO] Error al intentar conexión FTPS: " . $e->getMessage());
            }
        }

        if ($ftp === false) {
            @unlink($tempFile);
            throw new Exception("No se pudo conectar al servidor FTP de Mafarta en {$host}:{$port}.");
        }

        $login = @ftp_login($ftp, $user, $pass);
        if ($login === false) {
            @ftp_close($ftp);
            @unlink($tempFile);
            throw new Exception("Error de autenticación FTP en Mafarta con el usuario '{$user}'.");
        }

        if ($connection?->pasv ?? true) {
            @ftp_pasv($ftp, true);
        }

        if ($remoteDir !== '' && $remoteDir !== '/') {
            @ftp_chdir($ftp, $remoteDir);
        }

        $remotePath = ($remoteDir !== '' && $remoteDir !== '/') ? rtrim($remoteDir, '/') . '/' . $filename : $filename;

        // Subida en modo ASCII
        $upload = @ftp_put($ftp, $filename, $tempFile, FTP_ASCII);
        @ftp_close($ftp);
        @unlink($tempFile);

        if (!$upload) {
            Log::error("[MAFARTA PC-CORREO] Fallo al subir el archivo {$filename} a {$host}");
            throw new Exception("No se pudo subir el archivo de pedido {$filename} al servidor FTP de Mafarta.");
        }

        Log::info("[MAFARTA PC-CORREO] Pedido #{$autoOrder->id} transmitido exitosamente como {$filename}");

        return [
            'success' => true,
            'filename' => $filename,
            'remote_path' => $remotePath,
            'message' => "Pedido transmitido con éxito a Mafarta bajo protocolo PC-CORREO ({$filename}).",
        ];
    }

    /**
     * Transmite la orden de compra a Droguerías Cobeca / Mafarta mediante su API REST (comparadores).
     */
    public function sendOrderApi(AutoOrder $autoOrder): array
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $supplier = $autoOrder->supplier;
        if (!$supplier) {
            $supplier = Supplier::with('connections')->find($autoOrder->supplier_id);
        }

        if (!$supplier) {
            throw new Exception("No se encontró el proveedor asociado a la orden #{$autoOrder->id}");
        }

        // Obtener conexión API para Mafarta / Cobeca
        $connection = $supplier->connections()
            ->whereIn('type', ['api', 'http'])
            ->first()
            ?? $supplier->connections()->first();

        $username = $connection?->username ?: env('MAFARTA_USERNAME', 'F31373');
        $password = null;
        if ($connection && !empty($connection->password)) {
            try {
                $password = FtpCrypt::decrypt($connection->password);
            } catch (\Throwable $e) {
                $password = $connection->password;
            }
        }
        $password = $password ?: env('MAFARTA_PASSWORD', 'Mafarta2026*');

        // Obtener token mediante POST /api/Login o /api/auth/login
        $token = null;
        $loginUrl = 'https://comparadores.drogueriascobeca.com/api/Login';

        try {
            $loginResponse = Http::withoutVerifying()
                ->timeout(30)
                ->post($loginUrl, [
                    'Usuario' => $username,
                    'Clave' => $password,
                ]);

            if ($loginResponse->successful()) {
                $loginData = $loginResponse->json();
                $token = $loginData['token'] ?? $loginData['Token'] ?? null;
            }

            // Fallback con nombres alternativos de campos
            if (empty($token)) {
                $loginResponseAlt = Http::withoutVerifying()
                    ->timeout(30)
                    ->post($loginUrl, [
                        'User' => $username,
                        'Password' => $password,
                    ]);
                if ($loginResponseAlt->successful()) {
                    $loginData = $loginResponseAlt->json();
                    $token = $loginData['token'] ?? $loginData['Token'] ?? null;
                }
            }

            // Fallback al portal SIC de Cobeca si no retorna token
            if (empty($token)) {
                $sicLogin = Http::withoutVerifying()
                    ->timeout(30)
                    ->post('https://sic.drogueriascobeca.com/api/auth/login', [
                        'User' => $username,
                        'Password' => $password,
                    ]);
                if ($sicLogin->successful()) {
                    $token = $sicLogin->json('token');
                }
            }
        } catch (\Throwable $e) {
            Log::error("[MAFARTA API PEDIDO] Error al autenticar en Cobeca: " . $e->getMessage());
        }

        if (empty($token)) {
            throw new Exception("Error de autenticación con la API de Droguerías Cobeca. No se pudo obtener el token de acceso.");
        }

        $clientCode = (int) (preg_replace('/\D/', '', (string) $username) ?: env('MAFARTA_CLIENTE', 31373));
        $drugstoreCode = (int) env('MAFARTA_COD_DROGUERIA', 3); // 3 = Droguería Mafarta

        // Construir detalles del pedido
        $detalles = [];
        $secuencia = 1;
        $orderDateIso = ($autoOrder->created_at ? Carbon::parse($autoOrder->created_at) : Carbon::now())->toIso8601String();

        foreach ($autoOrder->details as $detail) {
            // Código interno del artículo en Cobeca/Mafarta
            $supplierItemCode = $detail->productSupplier?->cod_supplier;
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                if (!empty($detail->product_suppliers_id)) {
                    $psDirect = \App\Models\ProductSupplier::find($detail->product_suppliers_id);
                    $codeDirect = $psDirect?->cod_supplier;
                    if (!empty($codeDirect) && $codeDirect !== '0' && $codeDirect !== 0) {
                        $supplierItemCode = $codeDirect;
                    }
                }
            }

            // Buscar por código de barras si falta
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                $barcode = $detail->product?->barcode;
                if (!empty($barcode)) {
                    $psMafarta = \App\Models\ProductSupplier::where('supplier_id', $autoOrder->supplier_id)
                        ->where('barcode_match', $barcode)
                        ->whereNotNull('cod_supplier')
                        ->where('cod_supplier', '!=', '')
                        ->where('cod_supplier', '!=', '0')
                        ->first();
                    if ($psMafarta) {
                        $supplierItemCode = $psMafarta->cod_supplier;
                    }
                }
            }

            $codArticuloNum = (int) (preg_replace('/\D/', '', (string)$supplierItemCode) ?: ($detail->product_id ?? 0));
            $qty = (int) $detail->quantity;

            if ($qty > 0 && $codArticuloNum > 0) {
                $detalles[] = [
                    'Secuencia' => $secuencia++,
                    'Cod_articulo' => $codArticuloNum,
                    'Cantidad' => $qty,
                    'Fecha' => $orderDateIso,
                ];
            }
        }

        if (empty($detalles)) {
            throw new Exception("La orden #{$autoOrder->id} no contiene renglones válidos para transmitir a Droguerías Cobeca.");
        }

        $payload = [
            'Cod_cliente' => $clientCode,
            'Cod_drogueria' => $drugstoreCode,
            'Fecha' => $orderDateIso,
            'Odc' => (string) ($autoOrder->id ?? 1),
            'Detalles' => $detalles,
        ];

        Log::info("[MAFARTA API PEDIDO] Enviando orden #{$autoOrder->id} a Cobeca", [
            'url' => 'https://comparadores.drogueriascobeca.com/api/pedidos/comparador',
            'payload' => $payload,
        ]);

        $orderResponse = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withoutVerifying()
          ->timeout(60)
          ->post('https://comparadores.drogueriascobeca.com/api/pedidos/comparador', $payload);

        $responseStatus = $orderResponse->status();
        $responseBody = $orderResponse->json() ?? $orderResponse->body();

        Log::info("[MAFARTA API PEDIDO] Respuesta de Cobeca para orden #{$autoOrder->id}", [
            'status' => $responseStatus,
            'response' => $responseBody,
        ]);

        if ($orderResponse->successful()) {
            $resultado = is_array($responseBody) ? ($responseBody['Resultado'] ?? $responseBody['resultado'] ?? 'OK') : 'OK';

            return [
                'success' => true,
                'data' => $responseBody,
                'message' => "Pedido #{$autoOrder->id} transmitido exitosamente a Droguerías Cobeca (Mafarta) vía API REST. Resultado: {$resultado}",
            ];
        }

        $errorMsg = is_array($responseBody) ? ($responseBody['Message'] ?? $responseBody['message'] ?? json_encode($responseBody)) : $responseBody;
        throw new Exception("Error al transmitir el pedido a Droguerías Cobeca (HTTP {$responseStatus}): {$errorMsg}");
    }
}

