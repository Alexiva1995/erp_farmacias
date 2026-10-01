<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FactoryFiscalServiceInterface;
use App\Models\FiscalHistory;
use App\Models\GeneralSetting;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class FactoryFiscalService implements FactoryFiscalServiceInterface
{
    protected ?string $defaultIp = '127.0.0.1';
    protected int $defaultPort = 8090;
    protected int $timeoutSeconds = 5;

    public function __construct(
        protected ?FiscalZReportService $zReportService = null
    ) {
        $setting = GeneralSetting::first();
        if ($setting) {
            $this->defaultIp = $setting->factory_printer_ip ?: '127.0.0.1';
            $this->defaultPort = (int) ($setting->factory_printer_port ?: 8090);
        }
    }

    /**
     * Resolver la IP y Puerto destino.
     */
    protected function resolveEndpoint(?string $ip, ?int $port): array
    {
        $targetIp = !empty($ip) ? trim($ip) : $this->defaultIp;
        $targetPort = !empty($port) ? (int) $port : $this->defaultPort;

        return [$targetIp, $targetPort];
    }

    /**
     * Establecer conexión Socket TCP con el listener de The Factory HKA.
     *
     * @return resource|false
     */
    protected function openSocket(string $ip, int $port)
    {
        if (!extension_loaded('sockets')) {
            throw new Exception('La extensión PHP "sockets" no está habilitada en el servidor.');
        }

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $errorMsg = socket_strerror(socket_last_error());
            Log::error("[FactoryFiscal] Error en socket_create: {$errorMsg}");
            return false;
        }

        // Configurar timeouts de lectura/escritura (5 segundos)
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeoutSeconds, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeoutSeconds, 'usec' => 0]);

        $connected = @socket_connect($socket, $ip, $port);
        if ($connected === false) {
            $errorMsg = socket_strerror(socket_last_error($socket));
            Log::warning("[FactoryFiscal] No se pudo conectar a {$ip}:{$port} - {$errorMsg}");
            @socket_close($socket);
            return false;
        }

        return $socket;
    }

    /**
     * Enviar trama a través del socket y recibir respuesta.
     */
    protected function sendRaw($socket, string $payload): string
    {
        $in = $payload . "\0";
        $written = @socket_write($socket, $in, strlen($in));
        if ($written === false) {
            return '';
        }

        $out = @socket_read($socket, 2048);
        return $out !== false ? trim($out) : '';
    }

    /**
     * Enviar un comando fiscal individual.
     */
    protected function executeCmd($socket, string $cmd): bool
    {
        $out = $this->sendRaw($socket, "SendCmd():" . $cmd);
        $statusChar = substr($out, 10, 1);
        return $statusChar === 'T';
    }

    /**
     * Verificar conectividad con la impresora The Factory HKA.
     */
    public function checkConnection(?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        try {
            $socket = $this->openSocket($targetIp, $targetPort);
            if (!$socket) {
                return [
                    'success' => false,
                    'connected' => false,
                    'message' => "No se pudo establecer conexión TCP con {$targetIp}:{$targetPort}. Verifique que el servicio TCP Listener de The Factory HKA esté ejecutándose.",
                    'ip' => $targetIp,
                    'port' => $targetPort,
                ];
            }

            // Chequeo de presencia de impresora
            $outCheck = $this->sendRaw($socket, "CheckFprinter():1");
            $isPrinterPresent = (substr($outCheck, 10, 1) === 'T');

            // Lectura de estado
            $outStatus = $this->sendRaw($socket, "ReadFpStatus():1");
            $statusParts = explode("|", substr($outStatus, 10));

            @socket_close($socket);

            return [
                'success' => true,
                'connected' => true,
                'printer_present' => $isPrinterPresent,
                'status_code' => $statusParts[0] ?? 'N/A',
                'error_code' => $statusParts[2] ?? ($statusParts[1] ?? '0'),
                'raw_status' => $outStatus,
                'ip' => $targetIp,
                'port' => $targetPort,
                'message' => $isPrinterPresent
                    ? 'Conexión exitosa con la Impresora Fiscal The Factory HKA.'
                    : 'Conectado al listener TCP, pero la impresora fiscal no responde o está apagada.',
            ];
        } catch (Exception $e) {
            Log::error("[FactoryFiscal] checkConnection exception: " . $e->getMessage());
            return [
                'success' => false,
                'connected' => false,
                'message' => 'Excepción durante la verificación: ' . $e->getMessage(),
                'ip' => $targetIp,
                'port' => $targetPort,
            ];
        }
    }

    /**
     * Obtener el estado S1 de la impresora.
     */
    public function getStatusS1(?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        try {
            $socket = $this->openSocket($targetIp, $targetPort);
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "No se pudo conectar a {$targetIp}:{$targetPort}",
                ];
            }

            $out = $this->sendRaw($socket, "UploadStatus():S1");
            @socket_close($socket);

            $parts = explode("|", substr($out, 10));

            return [
                'success' => true,
                'cashier_number' => $parts[0] ?? null,
                'daily_sales_total' => $parts[1] ?? null,
                'last_invoice_number' => $parts[2] ?? null,
                'daily_invoice_count' => $parts[3] ?? null,
                'last_debit_note' => $parts[4] ?? null,
                'daily_debit_note_count' => $parts[5] ?? null,
                'last_credit_note' => $parts[6] ?? null,
                'daily_credit_note_count' => $parts[7] ?? null,
                'last_non_fiscal' => $parts[8] ?? null,
                'daily_non_fiscal_count' => $parts[9] ?? null,
                'audit_report_counter' => $parts[10] ?? null,
                'daily_closure_z_counter' => $parts[11] ?? null,
                'rif' => $parts[12] ?? null,
                'machine_serial' => $parts[13] ?? null,
                'printer_time' => $parts[14] ?? null,
                'printer_date' => $parts[15] ?? null,
                'raw_parts' => $parts,
            ];
        } catch (Exception $e) {
            Log::error("[FactoryFiscal] getStatusS1 exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Imprimir factura fiscal en The Factory HKA.
     */
    public function printInvoice(int $fiscalHistoryId, ?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        $fiscal = FiscalHistory::with(['details', 'order.client'])->find($fiscalHistoryId);
        if (!$fiscal) {
            return [
                'success' => false,
                'message' => "Registro FiscalHistory #{$fiscalHistoryId} no encontrado.",
            ];
        }

        $socket = $this->openSocket($targetIp, $targetPort);
        if (!$socket) {
            return [
                'success' => false,
                'message' => "No se pudo conectar con el listener The Factory en {$targetIp}:{$targetPort}.",
            ];
        }

        try {
            // 1. Encabezado del cliente
            $cleanName = $this->sanitizeText($fiscal->business_name ?: 'CLIENTE CONTADO', 40);
            $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($fiscal->identification ?: 'V000000000')));
            $cleanAddress = $this->sanitizeText($fiscal->address ?: 'LOCAL', 40);

            $commands = [];
            $commands[] = "iS*" . $cleanName;
            $commands[] = "iR*" . $cleanRif;
            $commands[] = "i00" . $cleanAddress;

            // 2. Líneas de detalle / productos
            foreach ($fiscal->details as $detail) {
                $qty = (float) ($detail->quantity ?? 1);
                $totalItemAmount = (float) ($detail->total_amount ?? 0);
                $unitPrice = $qty > 0 ? ($totalItemAmount / $qty) : $totalItemAmount;

                // Tasa de IVA:
                // ! = Exento (0%)
                // " = General (16%)
                // # = Reducida (8%)
                // $ = Adicional (31%)
                $isTaxable = ((int) ($detail->vat_status ?? 0) === 1) || ((float) ($detail->iva_amount ?? 0) > 0);
                $rateChar = $isTaxable ? '"' : '!';

                // Si es gravado, The Factory HKA espera el precio base (o neto según configuración).
                // Con tasa general 16%, base = total / 1.16
                $basePrice = $isTaxable ? ($unitPrice / 1.16) : $unitPrice;

                $priceFormatted = sprintf('%010d', (int) round($basePrice * 100)); // 10 dígitos (2 decimales enteros)
                $qtyFormatted = sprintf('%08d', (int) round($qty * 1000));          // 8 dígitos (3 decimales)
                $desc = $this->sanitizeText($detail->product_name ?: 'PRODUCTO', 38);

                $commands[] = $rateChar . $priceFormatted . $qtyFormatted . $desc;
            }

            // 3. Medios de pago / Cierre
            // 101 = Efectivo Bs / Cierre total
            $commands[] = "101";

            // 4. Enviar comandos secuencialmente
            foreach ($commands as $cmd) {
                $accepted = $this->executeCmd($socket, $cmd);
                if (!$accepted) {
                    // Leer error de la impresora
                    $outStatus = $this->sendRaw($socket, "ReadFpStatus():1");
                    @socket_close($socket);

                    Log::error("[FactoryFiscal] Error al ejecutar comando '{$cmd}'. Estado: {$outStatus}");
                    return [
                        'success' => false,
                        'message' => "La impresora fiscal rechazó el comando '{$cmd}'. Estado: {$outStatus}",
                        'failed_command' => $cmd,
                        'status' => $outStatus,
                    ];
                }
            }

            // 5. Obtener estado S1 para capturar el consecutivo fiscal y el serial
            $outS1 = $this->sendRaw($socket, "UploadStatus():S1");
            @socket_close($socket);

            $s1Parts = explode("|", substr($outS1, 10));
            $generatedInvoiceNumber = $s1Parts[2] ?? null;
            $machineSerial = $s1Parts[13] ?? null;

            // 6. Actualizar FiscalHistory
            $fiscal->update([
                'invoice_number' => $generatedInvoiceNumber ?: ($fiscal->invoice_number ?: 'N/A'),
                'fiscal_id' => $machineSerial ?: $fiscal->fiscal_id,
                'is_queued' => false,
                'invoice_date' => now(),
            ]);

            // Actualizar Order si existe
            if ($fiscal->order) {
                $fiscal->order->update([
                    'invoice_number' => $generatedInvoiceNumber ?: $fiscal->order->invoice_number,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Factura fiscal emitida exitosamente en The Factory HKA.',
                'invoice_number' => $generatedInvoiceNumber,
                'machine_serial' => $machineSerial,
                'fiscal_history_id' => $fiscal->id,
            ];
        } catch (Exception $e) {
            @socket_close($socket);
            Log::error("[FactoryFiscal] printInvoice exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error durante la emisión de la factura fiscal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Imprimir nota de crédito en The Factory HKA.
     */
    public function printCreditNote(array $data, ?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        $socket = $this->openSocket($targetIp, $targetPort);
        if (!$socket) {
            return [
                'success' => false,
                'message' => "No se pudo conectar a The Factory en {$targetIp}:{$targetPort}.",
            ];
        }

        try {
            $clientName = $this->sanitizeText($data['client_name'] ?? 'CLIENTE CONTADO', 40);
            $clientRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['client_rif'] ?? 'V000000000')));
            $origInvoice = str_pad(ltrim((string) ($data['invoice_number'] ?? '0'), '0'), 7, '0', STR_PAD_LEFT);
            $machineSerial = strtoupper(trim((string) ($data['machine_serial'] ?? '')));
            
            $dateRaw = $data['invoice_date'] ?? now()->format('Y-m-d');
            $dateFormatted = Carbon::parse($dateRaw)->format('d-m-Y');

            $commands = [];
            $commands[] = "iS*" . $clientName;
            $commands[] = "iR*" . $clientRif;
            $commands[] = "iF*" . $origInvoice;
            if (!empty($machineSerial)) {
                $commands[] = "iI*" . $machineSerial;
            }
            $commands[] = "iD*" . $dateFormatted;

            // Renglón de devolución:
            // d0 = Exento (0%)
            // d1 = General (16%)
            // d2 = Reducida (8%)
            // d3 = Adicional (31%)
            $isTaxable = !empty($data['is_taxable']);
            $devRate = $isTaxable ? 'd1' : 'd0';

            $refundAmount = (float) ($data['refund_amount'] ?? 0);
            $baseAmount = $isTaxable ? ($refundAmount / 1.16) : $refundAmount;

            $priceFormatted = sprintf('%010d', (int) round($baseAmount * 100));
            $qtyFormatted = '00001000'; // 1 unidad
            $description = $this->sanitizeText($data['description'] ?? 'DEVOLUCION DE MERCANCIA', 38);

            $commands[] = $devRate . $priceFormatted . $qtyFormatted . $description;
            $commands[] = "101";

            foreach ($commands as $cmd) {
                $accepted = $this->executeCmd($socket, $cmd);
                if (!$accepted) {
                    $outStatus = $this->sendRaw($socket, "ReadFpStatus():1");
                    @socket_close($socket);
                    return [
                        'success' => false,
                        'message' => "La impresora rechazó el comando de Nota de Crédito '{$cmd}'. Estado: {$outStatus}",
                    ];
                }
            }

            $outS1 = $this->sendRaw($socket, "UploadStatus():S1");
            @socket_close($socket);

            $s1Parts = explode("|", substr($outS1, 10));
            $creditNoteNumber = $s1Parts[6] ?? null;

            return [
                'success' => true,
                'message' => 'Nota de Crédito fiscal emitida exitosamente en The Factory HKA.',
                'credit_note_number' => $creditNoteNumber,
                'machine_serial' => $s1Parts[13] ?? null,
            ];
        } catch (Exception $e) {
            @socket_close($socket);
            Log::error("[FactoryFiscal] printCreditNote exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al emitir Nota de Crédito: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Imprimir Reporte X.
     */
    public function printReportX(?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        $socket = $this->openSocket($targetIp, $targetPort);
        if (!$socket) {
            return [
                'success' => false,
                'message' => "No se pudo conectar a The Factory en {$targetIp}:{$targetPort}.",
            ];
        }

        try {
            $accepted = $this->executeCmd($socket, "I0X");
            $outStatus = $this->sendRaw($socket, "ReadFpStatus():1");
            @socket_close($socket);

            return [
                'success' => $accepted,
                'message' => $accepted ? 'Reporte X impreso exitosamente.' : 'Error al imprimir Reporte X en la máquina fiscal.',
                'status' => $outStatus,
            ];
        } catch (Exception $e) {
            @socket_close($socket);
            return [
                'success' => false,
                'message' => 'Excepción en Reporte X: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Imprimir Reporte Z.
     */
    public function printReportZ(?string $ip = null, ?int $port = null): array
    {
        [$targetIp, $targetPort] = $this->resolveEndpoint($ip, $port);

        $socket = $this->openSocket($targetIp, $targetPort);
        if (!$socket) {
            return [
                'success' => false,
                'message' => "No se pudo conectar a The Factory en {$targetIp}:{$targetPort}.",
            ];
        }

        try {
            $accepted = $this->executeCmd($socket, "I0Z");
            
            $zNum = null;
            if ($accepted) {
                $outS1 = $this->sendRaw($socket, "UploadStatus():S1");
                $s1Parts = explode("|", substr($outS1, 10));
                $zNum = $s1Parts[11] ?? null;
            }

            $outStatus = $this->sendRaw($socket, "ReadFpStatus():1");
            @socket_close($socket);

            // Consolidar y cerrar Reporte Z en la base de datos si fue exitoso
            if ($accepted && $this->zReportService) {
                try {
                    $today = now()->format('Y-m-d');
                    $report = $this->zReportService->generateForDate($today, null, true);
                    if ($report) {
                        $updateData = [
                            'status' => 'closed',
                            'closing_time' => now()->format('H:i:s'),
                        ];
                        if ($zNum) {
                            $updateData['report_number'] = (int) $zNum;
                        }
                        $report->update($updateData);

                        $nextDate = Carbon::parse($today)->addDay()->format('Y-m-d');
                        $nextNum = ($zNum ?: $report->report_number) ? (($zNum ?: $report->report_number) + 1) : null;
                        $this->zReportService->openNextReport($nextDate, $nextNum);
                    }
                } catch (Exception $ex) {
                    Log::warning("[FactoryFiscal] No se pudo sincronizar el cierre Z en BD: " . $ex->getMessage());
                }
            }

            return [
                'success' => $accepted,
                'message' => $accepted ? 'Reporte Z diario cerrado e impreso exitosamente.' : 'Error al imprimir Reporte Z.',
                'z_number' => $zNum,
                'status' => $outStatus,
            ];
        } catch (Exception $e) {
            @socket_close($socket);
            return [
                'success' => false,
                'message' => 'Excepción en Reporte Z: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitizar texto para impresoras fiscales (ASCII/Mayúsculas, sin caracteres especiales inválidos).
     */
    protected function sanitizeText(string $text, int $maxLength = 40): string
    {
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $clean = preg_replace('/[^A-Za-z0-9\s\.\,\-\#\/\_]/', '', (string) $unaccented);
        $upper = strtoupper(trim((string) $clean));

        return substr($upper, 0, $maxLength);
    }
}
