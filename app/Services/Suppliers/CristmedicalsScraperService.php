<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\CristmedicalsScraperServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class CristmedicalsScraperService implements CristmedicalsScraperServiceInterface
{
    private const BASE_URL = 'https://cristmedicalsweb.cristmedicals.com';
    private const LOGIN_URL = 'https://cristmedicalsweb.cristmedicals.com/login';
    private const FACTURAS_URL = 'https://cristmedicalsweb.cristmedicals.com/facturas';
    private const PROXY_MOVILPAY_URL = 'https://cristmedicalsweb.cristmedicals.com/proxy_movilpay.php';
    private const REGISTRAR_PAGO_URL = 'https://cristmedicalsweb.cristmedicals.com/registrar-pago-confirmado';

    /**
     * Ejecuta una petición HTTP compatible con cualquier servidor (Linux / Windows / PHP-FPM / Apache / Nginx).
     */
    private function executeCurl(string $method, string $url, string $cookieFile, array $options = []): array
    {
        $headers = array_merge([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ], $options['headers'] ?? []);

        // 1. Intentar con la extensión PHP cURL nativa
        if (function_exists('curl_init')) {
            $ch = curl_init();
            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "{$k}: {$v}";
            }

            $curlOpts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_COOKIEJAR => $cookieFile,
                CURLOPT_COOKIEFILE => $cookieFile,
                CURLOPT_ENCODING => '',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => $curlHeaders,
            ];

            if (strtoupper($method) === 'POST') {
                $curlOpts[CURLOPT_POST] = true;
                if (isset($options['form_params'])) {
                    $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($options['form_params']);
                } elseif (isset($options['json'])) {
                    $curlOpts[CURLOPT_POSTFIELDS] = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $curlHeaders[] = 'Content-Type: application/json';
                    $curlOpts[CURLOPT_HTTPHEADER] = $curlHeaders;
                }
            }

            curl_setopt_array($ch, $curlOpts);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if ($response !== false && $statusCode > 0) {
                $headerText = substr($response, 0, $headerSize);
                $body = substr($response, $headerSize);
                return [
                    'status' => $statusCode,
                    'header' => $headerText,
                    'body' => $body,
                ];
            }
        }

        // 2. Fallback mediante binario curl del sistema
        $binary = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'curl.exe' : 'curl';
        $cookiePath = escapeshellarg($cookieFile);
        $userAgent = escapeshellarg("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36");
        $cmd = "{$binary} -s -i -k -L --compressed --connect-timeout 20 --max-time 45 -A {$userAgent} -b {$cookiePath} -c {$cookiePath}";

        foreach ($headers as $k => $v) {
            $cmd .= " -H " . escapeshellarg("{$k}: {$v}");
        }

        if (strtoupper($method) === 'POST') {
            $cmd .= " -X POST";
            if (isset($options['form_params'])) {
                $postData = http_build_query($options['form_params']);
                $cmd .= " -d " . escapeshellarg($postData);
            } elseif (isset($options['json'])) {
                $cmd .= " -H \"Content-Type: application/json\"";
                $json = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $cmd .= " -d " . escapeshellarg($json);
            }
        }

        $cmd .= " " . escapeshellarg($url);
        $output = shell_exec($cmd);

        if (!$output) {
            return ['status' => 0, 'header' => '', 'body' => ''];
        }

        $parts = explode("\r\n\r\n", $output);
        $body = array_pop($parts);
        while (!empty($parts) && trim($body) === '') {
            $body = array_pop($parts);
        }
        $headerText = end($parts) ?: '';

        preg_match('/HTTP\/\S+\s+(\d+)/', $headerText, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 200;

        return [
            'status' => $statusCode,
            'header' => $headerText,
            'body' => $body,
        ];
    }

    /**
     * Inicia sesión en el portal web de Cristmedicals y retorna el estado de la sesión.
     */
    private function createAuthenticatedSession(string $username, string $password): array
    {
        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $cookieDir = storage_path('framework/cache');
            if (!is_dir($cookieDir)) {
                @mkdir($cookieDir, 0777, true);
            }
            $cookieFile = $cookieDir . DIRECTORY_SEPARATOR . 'cristmedicals_' . md5($username . microtime()) . '.txt';

            try {
                // 1. Obtener token CSRF inicial desde la página de login
                $resLogin = $this->executeCurl('GET', self::LOGIN_URL, $cookieFile);

                $token = null;
                if (preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $resLogin['body'], $matches)) {
                    $token = $matches[1];
                } elseif (preg_match('/value=["\']([^"\']+)["\']\s+name=["\']_token["\']/i', $resLogin['body'], $matches)) {
                    $token = $matches[1];
                } elseif (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $resLogin['body'], $matches)) {
                    $token = $matches[1];
                }

                if (!$token) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException('No se pudo extraer el token CSRF del formulario de inicio de sesión de Cristmedicals.');
                }

                // 2. Enviar credenciales
                $loginPost = $this->executeCurl('POST', self::LOGIN_URL, $cookieFile, [
                    'form_params' => [
                        '_token' => $token,
                        'login' => $username,
                        'password' => $password,
                    ],
                ]);

                if ($loginPost['status'] >= 400 && $loginPost['status'] !== 302) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException("Fallo la autenticación en el portal Cristmedicals. Código de estado: {$loginPost['status']}");
                }

                // 3. Obtener token CSRF fresco de la sesión activa y HTML de facturas
                $resFacturas = $this->executeCurl('GET', self::FACTURAS_URL, $cookieFile);

                // Si la respuesta contiene el formulario de login o no está autenticado
                if (str_contains($resFacturas['body'], 'form-signin') || str_contains($resFacturas['body'], 'action="https://cristmedicalsweb.cristmedicals.com/login"') || (!str_contains($resFacturas['body'], 'tabla-facturas') && !str_contains($resFacturas['body'], 'factura-check') && str_contains($resFacturas['body'], 'Iniciar Sesión'))) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException("Credenciales inválidas o inicio de sesión rechazado en el portal de Cristmedicals para el usuario '{$username}'.");
                }

                $freshToken = null;
                if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $resFacturas['body'], $matches)) {
                    $freshToken = $matches[1];
                } elseif (preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $resFacturas['body'], $matches)) {
                    $freshToken = $matches[1];
                }

                return [
                    'cookie_file' => $cookieFile,
                    'csrf_token' => $freshToken ?: $token,
                    'facturas_html' => $resFacturas['body'],
                ];
            } catch (\Throwable $e) {
                if (file_exists($cookieFile)) {
                    @unlink($cookieFile);
                }
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep(500000); // 0.5s
                }
            }
        }

        throw ($lastException ?: new \RuntimeException('Error al autenticar con el portal Cristmedicals tras múltiples intentos.'));
    }

    /**
     * Extrae el listado de facturas pendientes directamente del portal web de Cristmedicals.
     */
    public function fetchInvoices(string $username, string $password): array
    {
        $session = $this->createAuthenticatedSession($username, $password);
        $html = $session['facturas_html'];

        if (file_exists($session['cookie_file'])) {
            @unlink($session['cookie_file']);
        }

        return $this->parseInvoicesFromHtml($html);
    }

    /**
     * Parsea la tabla HTML de facturas extraída de /facturas.
     */
    private function parseInvoicesFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//table//tbody/tr');
        $extracted = [];

        foreach ($rows as $row) {
            $checkbox = $xpath->query('.//input[contains(@class, "factura-check")]', $row)->item(0);
            if (!$checkbox) {
                continue;
            }

            $numFactura = trim($checkbox->getAttribute('data-numero') ?: $checkbox->getAttribute('value'));
            $montoNetoUsd = (float) $checkbox->getAttribute('data-monto');
            $saldoConDescUsd = (float) $checkbox->getAttribute('data-descuento');
            $totalBs = (float) $checkbox->getAttribute('data-bs');

            // Extraer columnas de texto
            $tds = $xpath->query('.//td', $row);
            $emisionRaw = $tds->item(2) ? trim($tds->item(2)->textContent) : null;
            $vencimientoRaw = $tds->item(3) ? trim($tds->item(3)->textContent) : null;
            $moraRaw = $tds->item(4) ? trim($tds->item(4)->textContent) : '0';
            $descuentoPorc = $tds->item(7) ? trim($tds->item(7)->textContent) : '0%';
            $montoDescUsd = $tds->item(8) ? (float) trim($tds->item(8)->textContent) : 0.0;

            // Formatear fechas
            $emision = null;
            if (!empty($emisionRaw)) {
                try {
                    $emision = Carbon::createFromFormat('d-m-Y', $emisionRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $emision = Carbon::parse($emisionRaw)->format('Y-m-d');
                }
            }

            $vencimiento = null;
            if (!empty($vencimientoRaw)) {
                try {
                    $vencimiento = Carbon::createFromFormat('d-m-Y', $vencimientoRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $vencimiento = Carbon::parse($vencimientoRaw)->format('Y-m-d');
                }
            }

            $extracted[] = [
                'num_factura' => $numFactura,
                'emision' => $emision,
                'vencimiento' => $vencimiento,
                'mora' => (int) $moraRaw,
                'total_neto_usd' => $montoNetoUsd,
                'descuento_porcentaje' => $descuentoPorc,
                'monto_desc_usd' => $montoDescUsd,
                'saldo_con_desc_usd' => $saldoConDescUsd,
                'total_bs' => $totalBs,
            ];
        }

        return $extracted;
    }

    /**
     * Sincroniza las facturas de Cristmedicals en la base de datos del ERP:
     * - Actualiza fechas de vencimiento reales, montos con descuento en USD y monto real a pagar en Bs.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array
    {
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%CRISTALMEDICALS%')
                ->orWhere('name', 'LIKE', '%CRIST%')
                ->orWhere('id', 1002)
                ->first();
            $supplierId = $supplier?->id ?? 1002;
        }

        $conn = $supplier?->connections?->first();

        $user = $username;
        $pass = $password;

        if (!$user && $conn && !empty($conn->username)) {
            $user = $conn->username;
        }
        if (!$pass && $conn && !empty($conn->password)) {
            try {
                $pass = FtpCrypt::decrypt($conn->password);
            } catch (\Throwable) {
                $pass = null;
            }
        }

        $user = $user ?: env('CRISTMEDICALS_USERNAME', 'FAR00818');
        $pass = $pass ?: env('CRISTMEDICALS_PASSWORD', 'FAR00818');

        $invoices = $this->fetchInvoices($user, $pass);

        if (empty($invoices)) {
            return [
                'total_extracted' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'supplier_id' => $supplierId,
                'details' => [],
            ];
        }

        if ($onlyInvoice) {
            $targetNum = ltrim((string) $onlyInvoice, '0');
            $invoices = array_values(array_filter($invoices, function ($inv) use ($targetNum, $onlyInvoice) {
                $clean = ltrim((string) $inv['num_factura'], '0');
                return $clean === $targetNum || $inv['num_factura'] === $onlyInvoice;
            }));
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $processed = [];
        $today = Carbon::now()->format('Y-m-d');
        $userId = User::first()?->id ?? 1;

        foreach ($invoices as $invData) {
            $rawDocNum = (string) ($invData['num_factura'] ?? '');
            $cleanNumber = ltrim($rawDocNum, '0');

            if (empty($cleanNumber)) {
                $skippedCount++;
                continue;
            }

            $possibleNumbers = array_unique([
                $rawDocNum,
                $cleanNumber,
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
            ]);

            $invoice = Invoice::where('supplier_id', $supplierId)
                ->where(function ($query) use ($possibleNumbers) {
                    $query->whereIn('invoice_number', $possibleNumbers);
                })
                ->first();

            $expDate = $invData['vencimiento'] ?: $today;
            $emisionDate = $invData['emision'] ?: $today;
            $totalBs = (float) ($invData['total_bs'] ?? 0);
            $saldoConDescUsd = (float) ($invData['saldo_con_desc_usd'] ?? 0);
            $totalNetoUsd = (float) ($invData['total_neto_usd'] ?? 0);

            if ($invoice) {
                $updateData = [
                    'exp_date' => $expDate,
                    'total_amount_discount' => $saldoConDescUsd > 0 ? $saldoConDescUsd : $invoice->total_amount_discount,
                    'net_payable_amount' => $totalBs > 0 ? $totalBs : $invoice->net_payable_amount,
                ];

                if ((int) ($invoice->status_payment ?? 0) !== 1) {
                    $updateData['payment_date'] = $expDate;
                }

                if ($emisionDate) {
                    $updateData['created_invoice_date'] = $emisionDate;
                }

                // Guardar el valor exacto a pagar en USD (priorizando el saldo con descuento sincronizado)
                if ($saldoConDescUsd > 0) {
                    $updateData['total_usd'] = $saldoConDescUsd;
                } elseif ($totalNetoUsd > 0) {
                    $updateData['total_usd'] = $totalNetoUsd;
                }

                // Guardar el monto real en Bs calculado por Cristmedicals
                if ($totalBs > 0) {
                    $updateData['total_amount'] = $totalBs;
                }

                $invoice->update($updateData);
                $updatedCount++;

                $processed[] = [
                    'invoice_number' => $invoice->invoice_number,
                    'action' => 'updated',
                    'exp_date' => $expDate,
                    'total_usd' => (float) ($updateData['total_usd'] ?? $invoice->total_usd),
                    'saldo_con_desc_usd' => $saldoConDescUsd,
                    'total_bs' => $totalBs,
                ];
            } else {
                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'invoice_number' => $rawDocNum,
                    'control_number' => null,
                    'created_invoice_date' => $emisionDate,
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'currency' => 'USD',
                    'exchange_rate' => 1.00,
                    'exempt_amount' => 0,
                    'taxable_base' => $totalNetoUsd,
                    'tax_amount' => 0,
                    'total_amount' => $totalBs > 0 ? $totalBs : $totalNetoUsd,
                    'total_usd' => $totalNetoUsd,
                    'total_amount_discount' => $saldoConDescUsd,
                    'net_payable_amount' => $totalBs,
                    'is_indexed' => false,
                    'status' => 'pending',
                    'status_payment' => 0,
                    'uploaded_by' => $userId,
                    'registered_by' => $userId,
                ]);

                $createdCount++;

                $processed[] = [
                    'invoice_number' => $newInvoice->invoice_number,
                    'action' => 'created',
                    'exp_date' => $expDate,
                    'total_usd' => $totalNetoUsd,
                    'saldo_con_desc_usd' => $saldoConDescUsd,
                    'total_bs' => $totalBs,
                ];
            }
        }

        // Análisis de discrepancias entre el ERP y Cristmedicals
        $portalPendingMap = [];
        foreach ($invoices as $invData) {
            $num = (string) ($invData['num_factura'] ?? '');
            $clean = ltrim($num, '0');
            $portalPendingMap[$num] = $invData;
            if ($clean !== '') {
                $portalPendingMap[$clean] = $invData;
            }
        }

        $allErpInvoices = Invoice::where('supplier_id', $supplierId)->get();
        $paidInErpPendingInCristmedicals = [];
        $pendingInErpPaidInCristmedicals = [];

        foreach ($allErpInvoices as $erpInv) {
            $rawNum = (string) $erpInv->invoice_number;
            $cleanNum = ltrim($rawNum, '0');
            $isPendingInPortal = isset($portalPendingMap[$rawNum]) || isset($portalPendingMap[$cleanNum]);
            $isPaidInErp = ((int) $erpInv->status_payment === 1);

            if ($isPaidInErp && $isPendingInPortal) {
                $portalDoc = $portalPendingMap[$rawNum] ?? $portalPendingMap[$cleanNum];
                $paidInErpPendingInCristmedicals[] = [
                    'id' => $erpInv->id,
                    'invoice_number' => $erpInv->invoice_number,
                    'control_number' => $erpInv->control_number,
                    'amount' => $erpInv->total_amount,
                    'currency' => $erpInv->currency,
                    'portal_amount' => $portalDoc['total_bs'] ?? $erpInv->net_payable_amount,
                    'portal_amount_usd' => $portalDoc['saldo_con_desc_usd'] ?? $erpInv->total_amount_discount,
                    'erp_status' => 'Pagada en ERP',
                    'portal_status' => 'Pendiente en Cristmedicals',
                ];
            } elseif (!$isPaidInErp && !$isPendingInPortal) {
                $pendingInErpPaidInCristmedicals[] = [
                    'id' => $erpInv->id,
                    'invoice_number' => $erpInv->invoice_number,
                    'control_number' => $erpInv->control_number,
                    'amount' => $erpInv->total_amount,
                    'currency' => $erpInv->currency,
                    'erp_status' => 'Pendiente en ERP',
                    'portal_status' => 'Liquidada en Cristmedicals',
                ];
            }
        }

        return [
            'total_extracted' => count($invoices),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'discrepancies' => [
                'paid_in_erp_pending_in_cristmedicals' => $paidInErpPendingInCristmedicals,
                'pending_in_erp_paid_in_cristmedicals' => $pendingInErpPaidInCristmedicals,
                'total_discrepancies' => count($paidInErpPendingInCristmedicals) + count($pendingInErpPaidInCristmedicals),
            ],
            'details' => $processed,
        ];
    }

    /**
     * Reporta y procesa un pago directamente en el portal web de Cristmedicals.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank = '30',
        ?string $paymentDate = null,
        string $paymentMethod = '2'
    ): array {
        Log::info('[CRISTMEDICALS PAYMENT] Iniciando reporte de pago para facturas: ' . implode(', ', $invoiceNumbers));

        $supplier = Supplier::with('connections')
            ->where('name', 'LIKE', '%CRIST%')
            ->orWhere('id', 1002)
            ->first();

        $pass = null;
        if ($conn && !empty($conn->password)) {
            try {
                $pass = FtpCrypt::decrypt($conn->password);
            } catch (\Throwable) {
                $pass = null;
            }
        }
        $pass = $pass ?: env('CRISTMEDICALS_PASSWORD', 'FAR00818');

        $session = $this->createAuthenticatedSession($user, $pass);
        $cookieFile = $session['cookie_file'];
        $csrfToken = $session['csrf_token'];
        $currentInvoices = $this->parseInvoicesFromHtml($session['facturas_html']);

        try {
            // Filtrar las facturas a pagar
            $targetNumbers = array_map(fn($n) => ltrim((string) $n, '0'), $invoiceNumbers);
            $matchedFacturas = array_values(array_filter($currentInvoices, function ($inv) use ($targetNumbers, $invoiceNumbers) {
                $clean = ltrim((string) $inv['num_factura'], '0');
                return in_array($clean, $targetNumbers) || in_array($inv['num_factura'], $invoiceNumbers);
            }));

            if (empty($matchedFacturas)) {
                Log::warning('[CRISTMEDICALS PAYMENT] No se encontraron las facturas seleccionadas en el portal web.');
                $matchedFacturas = array_map(fn($num) => [
                    'num_factura' => (string) $num,
                    'total_neto_usd' => 0,
                    'saldo_con_desc_usd' => 0,
                    'total_bs' => $paymentAmount,
                    'monto_desc_usd' => 0,
                ], $invoiceNumbers);
            }

            // Calcular total en Bs de las facturas seleccionadas
            $totalBsSum = array_sum(array_column($matchedFacturas, 'total_bs'));
            $finalAmountBs = ($totalBsSum > 0) ? round($totalBsSum, 2) : round($paymentAmount, 2);

            $dateFormatted = $paymentDate ? Carbon::parse($paymentDate)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            // 1. Validar pago con MovilPay a través del proxy
            $datosValidate = [
                'amount' => number_format($finalAmountBs, 2, '.', ''),
                'reference' => (string) $reference,
                'bank_destiny' => $destinationBank ?: '30',
                'method' => (string) $paymentMethod,
                'date' => $dateFormatted,
                'mobile' => '',
                'sender' => '',
            ];

            Log::info('[CRISTMEDICALS PAYMENT] Validando pago en proxy MovilPay...', $datosValidate);

            $resValidate = $this->executeCurl('POST', self::PROXY_MOVILPAY_URL . '?endpoint=validate', $cookieFile, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => $datosValidate,
            ]);

            $validateData = json_decode($resValidate['body'], true);

            if ($resValidate['status'] >= 400 || (isset($validateData['status']) && $validateData['status'] === 'error')) {
                $msg = $validateData['message'] ?? 'Error al validar pago en el banco.';
                Log::error("[CRISTMEDICALS PAYMENT] Error validando con MovilPay: {$msg}");
                return [
                    'success' => false,
                    'message' => $msg,
                    'validate_response' => $validateData,
                ];
            }

            // 2. Normalizar facturas para registro
            $facturasNormalizadas = array_map(function ($f) {
                return [
                    'factura' => (string) $f['num_factura'],
                    'saldo_neto' => (float) ($f['total_neto_usd'] ?? 0),
                    'saldo_con_descuento' => (float) ($f['saldo_con_desc_usd'] ?? 0),
                    'monto_bs' => (float) ($f['total_bs'] ?? 0),
                    'descuento_pp_usd' => (float) ($f['monto_desc_usd'] ?? 0),
                ];
            }, $matchedFacturas);

            // 3. Registrar pago confirmado en el portal Laravel de Cristmedicals
            $bodyRegistro = array_merge(is_array($validateData) ? $validateData : [], [
                'reference' => (string) $reference,
                'co_cli' => $user,
                'facturas' => $facturasNormalizadas,
            ]);

            Log::info('[CRISTMEDICALS PAYMENT] Registrando pago en el portal Cristmedicals...', $bodyRegistro);

            $resRegistro = $this->executeCurl('POST', self::REGISTRAR_PAGO_URL, $cookieFile, [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-CSRF-TOKEN' => $csrfToken,
                ],
                'json' => $bodyRegistro,
            ]);

            $registroData = json_decode($resRegistro['body'], true);
            $success = ($resRegistro['status'] >= 200 && $resRegistro['status'] < 300);

            Log::info('[CRISTMEDICALS PAYMENT] Resultado de registro:', [
                'status' => $resRegistro['status'],
                'response' => $registroData,
            ]);

            return [
                'success' => $success,
                'message' => $success ? 'Pago validado y registrado exitosamente en Cristmedicals.' : 'El banco validó el pago pero hubo un error al registrarlo en el portal.',
                'amount_bs' => $finalAmountBs,
                'reference' => $reference,
                'facturas' => $facturasNormalizadas,
                'data' => $registroData,
            ];
        } finally {
            if (file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }
}