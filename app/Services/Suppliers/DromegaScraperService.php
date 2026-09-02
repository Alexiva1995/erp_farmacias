<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DromegaScraperServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\Invoice;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DromegaScraperService implements DromegaScraperServiceInterface
{
    private const BASE_URL = 'https://www.drogueriamega.com';
    private const LOGIN_URL = 'https://www.drogueriamega.com/ventas/wp-login.php';
    private const ESTADO_CUENTA_URL = 'https://www.drogueriamega.com/ventas/estado-de-cuenta/?cliente=7586';

    /**
     * Ejecuta una petición HTTP usando cURL nativo de PHP con cabeceras de navegador.
     */
    private function executeCurl(string $url, string $cookieString, array $options = []): array
    {
        $headers = array_merge([
            "Cookie: {$cookieString}",
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: es-VE,es-419;q=0.9,es;q=0.8',
            'Cache-Control: max-age=0',
            'Referer: https://www.drogueriamega.com/ventas/',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'sec-ch-ua: "Chromium";v="152", "Not?A_Brand";v="24", "Google Chrome";v="152"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
        ], $options['headers'] ?? []);

        $ch = curl_init();
        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (isset($options['post'])) {
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = is_array($options['post']) ? http_build_query($options['post']) : $options['post'];
        }

        curl_setopt_array($ch, $curlOpts);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($response === false) {
            return ['status' => 0, 'header' => '', 'body' => '', 'url' => ''];
        }

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'status' => $statusCode,
            'header' => $headerText,
            'body' => $body,
            'url' => $effectiveUrl,
        ];
    }

    /**
     * Extrae las facturas directamente del estado de cuenta de Droguería Mega.
     */
    public function fetchInvoices(?string $cookie = null, ?string $user = null, ?string $pass = null): array
    {
        $cookieString = $cookie ?: env('DROMEGA_COOKIE', 'wordpress_test_cookie=WP%20Cookie%20check; wp_lang=es_ES; wordpress_logged_in_39574764368bb892fdea55c61228e833=Farmacia_Barrio_Sucre%7C1789522005%7CYWx0d9WkwLcNilkn5JDCcVxXwC4xCWiXdW5dXvzvmCb%7Cd8a89bfde4906ecd86eabc0061b580cce09bb1b71de7a7f85fe54ec1657bed9d; _ga=GA1.1.786654209.1780670257; _ga_J50XJCL6NJ=GS2.1.s1780670257$o1$g0$t1780670272$j45$l0$h0; PHPSESSID=394ae3b6804e7d2b6e052a44b2cdd93d');

        $res = $this->executeCurl(self::ESTADO_CUENTA_URL, $cookieString);

        if ($res['status'] !== 200 || empty($res['body'])) {
            throw new \RuntimeException("No se pudo obtener el estado de cuenta de Droguería Mega (HTTP {$res['status']}).");
        }

        if (str_contains($res['body'], 'loginform') || str_contains($res['body'], 'user_login') || str_contains($res['url'], 'login')) {
            throw new \RuntimeException("La sesión de Droguería Mega ha expirado o no es válida. Se requiere actualizar la cookie de sesión.");
        }

        return $this->parseInvoicesFromHtml($res['body']);
    }

    /**
     * Parsea el HTML del estado de cuenta para extraer el detalle de cada factura.
     */
    private function parseInvoicesFromHtml(string $html): array
    {
        $invoices = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table');

        $targetTable = null;
        foreach ($tables as $table) {
            $tableText = $table->textContent;
            if (str_contains($tableText, 'VENCIMIENTOPROTECCIÓN') || str_contains($tableText, 'PROTECCIÓNTASA') || str_contains($tableText, 'SALDO BS.')) {
                $targetTable = $table;
                break;
            }
        }

        if (!$targetTable) {
            return [];
        }

        $rows = $xpath->query('.//tr', $targetTable);
        $today = Carbon::today();

        foreach ($rows as $index => $row) {
            $cells = $xpath->query('.//td', $row);
            if ($cells->length < 13) {
                continue;
            }

            $emisionRaw = trim($cells->item(0)->textContent);
            $entregaRaw = trim($cells->item(1)->textContent);
            $vencimientoRaw = trim($cells->item(2)->textContent);
            $diasCreditoRaw = trim($cells->item(3)->textContent);
            $vencimientoProteccionRaw = trim($cells->item(4)->textContent);
            $diasProteccionRaw = trim($cells->item(5)->textContent);
            $documentoRaw = trim($cells->item(6)->textContent);
            $tipoRaw = trim($cells->item(7)->textContent);
            $montoBrutoRaw = trim($cells->item(8)->textContent);
            $montoNetoRaw = trim($cells->item(9)->textContent);
            $impuestoRaw = trim($cells->item(10)->textContent);
            $saldoUsdRaw = trim($cells->item(11)->textContent);
            $saldoBsRaw = trim($cells->item(12)->textContent);

            // Ignorar fila de encabezado o total
            if (
                stripos($emisionRaw, 'TOTAL') !== false ||
                stripos($emisionRaw, 'EMISIÓN') !== false ||
                stripos($documentoRaw, 'DOCUMENTO') !== false ||
                empty($documentoRaw) ||
                !preg_match('/\d+/', $documentoRaw)
            ) {
                continue;
            }

            // Parsear fechas (formato d/m/Y)
            $emision = $this->parseDate($emisionRaw);
            $entrega = $this->parseDate($entregaRaw);
            $vencimiento = $this->parseDate($vencimientoRaw);
            $vencimientoProteccion = $this->parseDate($vencimientoProteccionRaw);

            // Parsear números
            $montoBruto = $this->parseAmount($montoBrutoRaw);
            $montoNeto = $this->parseAmount($montoNetoRaw);
            $saldoUsd = $this->parseAmount($saldoUsdRaw);
            $saldoBs = $this->parseAmount($saldoBsRaw);

            // Reglas de negocio de Droguería Mega:
            // 1. Fecha de vencimiento: La indicada en el portal
            $expDate = $vencimiento ?: ($vencimientoProteccion ?: $today->format('Y-m-d'));

            // 2. Fecha de pago: Es la fecha de protección de tasa, EXCEPTO si fecha de protección == fecha de entrega
            // Si la fecha de protección de tasa es la misma que la fecha de entrega, la fecha de pago es la de vencimiento
            $isSameDayProtection = ($entrega && $vencimientoProteccion && $entrega === $vencimientoProteccion);
            
            if ($isSameDayProtection) {
                $paymentDate = $vencimiento ?: $expDate;
                $isIndexed = true;
            } else {
                $paymentDate = $vencimientoProteccion ?: $expDate;
                // Indexada cuando ya pasó la fecha de protección de tasa
                $isIndexed = $vencimientoProteccion ? $today->gt(Carbon::parse($vencimientoProteccion)) : false;
            }

            $invoices[] = [
                'num_factura' => $documentoRaw,
                'emision' => $emision,
                'entrega' => $entrega,
                'vencimiento' => $vencimiento,
                'vencimiento_proteccion' => $vencimientoProteccion,
                'dias_credito' => (int) $diasCreditoRaw,
                'dias_proteccion' => (int) $diasProteccionRaw,
                'tipo' => $tipoRaw,
                'monto_bruto' => $montoBruto,
                'monto_neto' => $montoNeto,
                'saldo_usd' => $saldoUsd,
                'saldo_bs' => $saldoBs,
                'payment_date' => $paymentDate,
                'exp_date' => $expDate,
                'is_indexed' => $isIndexed,
            ];
        }

        return $invoices;
    }

    /**
     * Parsea fechas en formato dd/mm/yyyy o dd-mm-yyyy hacia Y-m-d.
     */
    private function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $clean = trim(str_replace(['/', '.'], '-', $dateStr));
        try {
            return Carbon::createFromFormat('d-m-Y', $clean)->format('Y-m-d');
        } catch (\Throwable) {
            try {
                return Carbon::parse($clean)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * Convierte cadenas monetarias con separador de miles '.' y decimales ',' a float.
     */
    private function parseAmount(?string $amountStr): float
    {
        if (empty($amountStr)) {
            return 0.0;
        }

        $clean = trim($amountStr);
        // Quitar espacios y símbolos de moneda
        $clean = preg_replace('/[^\d,.-]/', '', $clean);

        // Si tiene formato latino 1.234,56
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    /**
     * Sincroniza las facturas de Droguería Mega en la base de datos del ERP.
     */
    public function syncInvoices(?string $cookie = null, ?string $username = null, ?string $password = null, ?int $supplierId = null): array
    {
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%DROMEGA%')
                ->orWhere('name', 'LIKE', '%MEGA%')
                ->orWhere('id', 1005)
                ->first();
            $supplierId = $supplier?->id ?? 1005;
        }

        $conn = $supplier?->connections?->first();

        // Obtener cookie de sesión
        $activeCookie = $cookie;
        if (!$activeCookie && $conn && !empty($conn->path) && str_contains($conn->path, 'wordpress_logged_in_')) {
            $activeCookie = $conn->path;
        }

        $extractedInvoices = $this->fetchInvoices($activeCookie);

        if (empty($extractedInvoices)) {
            return [
                'total_extracted' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'supplier_id' => $supplierId,
                'details' => [],
            ];
        }

        $createdCount = 0;
        $updatedCount = 0;
        $processed = [];
        $today = Carbon::today()->format('Y-m-d');

        foreach ($extractedInvoices as $invData) {
            $rawDocNum = (string) $invData['num_factura'];
            $cleanNumber = ltrim($rawDocNum, '0');

            $possibleNumbers = array_unique([
                $rawDocNum,
                $cleanNumber,
                str_pad($cleanNumber, 6, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
            ]);

            $invoice = Invoice::where('supplier_id', $supplierId)
                ->where(function ($query) use ($possibleNumbers) {
                    $query->whereIn('invoice_number', $possibleNumbers);
                })
                ->first();

            $expDate = $invData['exp_date'] ?: $today;
            $paymentDate = $invData['payment_date'] ?: $expDate;
            $emisionDate = $invData['emision'] ?: $today;
            $entregaDate = $invData['entrega'] ?: $today;
            $totalBs = (float) ($invData['saldo_bs'] ?? 0);
            $totalUsd = (float) ($invData['saldo_usd'] ?? 0);
            $isIndexed = (bool) ($invData['is_indexed'] ?? false);

            if ($invoice) {
                $updateData = [
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'net_payable_amount' => $totalBs > 0 ? $totalBs : $invoice->net_payable_amount,
                ];

                if ((int) ($invoice->status_payment ?? 0) !== 1) {
                    $updateData['payment_date'] = $paymentDate;
                }

                if ($emisionDate) {
                    $updateData['created_invoice_date'] = $emisionDate;
                }
                if ($entregaDate) {
                    $updateData['received_date'] = $entregaDate;
                }
                if ($totalUsd > 0 && ((float) ($invoice->total_usd ?? 0) <= 0)) {
                    $updateData['total_usd'] = $totalUsd;
                    $updateData['original_amount_usd'] = $totalUsd;
                }

                $invoice->update($updateData);
                $updatedCount++;

                $processed[] = [
                    'invoice_number' => $invoice->invoice_number,
                    'action' => 'updated',
                    'payment_date' => $paymentDate,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_usd' => (float) $invoice->total_usd,
                    'total_bs' => $totalBs,
                ];
            } else {
                $userId = auth()->id() ?? \App\Models\User::first()?->id ?? 1;
                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'uploaded_by' => $userId,
                    'registered_by' => $userId,
                    'loaded_by' => $userId,
                    'invoice_number' => $rawDocNum,
                    'control_number' => '00-' . str_pad($cleanNumber, 7, '0', STR_PAD_LEFT),
                    'created_invoice_date' => $emisionDate,
                    'received_date' => $entregaDate,
                    'exp_date' => $expDate,
                    'payment_date' => $paymentDate,
                    'currency' => 'USD',
                    'original_amount_usd' => $totalUsd,
                    'total_usd' => $totalUsd,
                    'total_amount' => $totalUsd,
                    'exempt_amount' => 0,
                    'taxable_base' => 0,
                    'tax_amount' => 0,
                    'net_payable_amount' => $totalBs,
                    'is_indexed' => $isIndexed,
                    'status' => 'pending',
                    'status_payment' => 0,
                ]);
                $createdCount++;

                $processed[] = [
                    'invoice_number' => $newInvoice->invoice_number,
                    'action' => 'created',
                    'payment_date' => $paymentDate,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_usd' => $totalUsd,
                    'total_bs' => $totalBs,
                ];
            }
        }

        // Análisis de discrepancias entre el ERP y Droguería Mega
        $portalPendingMap = [];
        foreach ($extractedInvoices as $invData) {
            $num = (string) ($invData['num_factura'] ?? '');
            $clean = ltrim($num, '0');
            $portalPendingMap[$num] = $invData;
            if ($clean !== '') {
                $portalPendingMap[$clean] = $invData;
            }
        }

        $allErpInvoices = Invoice::where('supplier_id', $supplierId)->get();
        $paidInErpPendingInDromega = [];
        $pendingInErpPaidInDromega = [];

        foreach ($allErpInvoices as $erpInv) {
            $rawNum = (string) $erpInv->invoice_number;
            $cleanNum = ltrim($rawNum, '0');
            $isPendingInPortal = isset($portalPendingMap[$rawNum]) || isset($portalPendingMap[$cleanNum]);
            $isPaidInErp = ((int) $erpInv->status_payment === 1);

            if ($isPaidInErp && $isPendingInPortal) {
                $portalDoc = $portalPendingMap[$rawNum] ?? $portalPendingMap[$cleanNum];
                $paidInErpPendingInDromega[] = [
                    'id' => $erpInv->id,
                    'invoice_number' => $erpInv->invoice_number,
                    'control_number' => $erpInv->control_number,
                    'amount' => $erpInv->total_amount,
                    'currency' => $erpInv->currency,
                    'portal_amount' => $portalDoc['saldo_bs'] ?? $erpInv->net_payable_amount,
                    'portal_amount_usd' => $portalDoc['saldo_usd'] ?? $erpInv->total_usd,
                    'erp_status' => 'Pagada en ERP',
                    'portal_status' => 'Pendiente en Droguería Mega',
                ];
            } elseif (!$isPaidInErp && !$isPendingInPortal) {
                $pendingInErpPaidInDromega[] = [
                    'id' => $erpInv->id,
                    'invoice_number' => $erpInv->invoice_number,
                    'control_number' => $erpInv->control_number,
                    'amount' => $erpInv->total_amount,
                    'currency' => $erpInv->currency,
                    'erp_status' => 'Pendiente en ERP',
                    'portal_status' => 'Liquidada en Droguería Mega',
                ];
            }
        }

        Log::info("[DROMEGA SCRAPER] Sincronización completada. Creadas: {$createdCount}, Actualizadas: {$updatedCount}, Discrepancias: " . (count($paidInErpPendingInDromega) + count($pendingInErpPaidInDromega)));

        return [
            'total_extracted' => count($extractedInvoices),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => 0,
            'supplier_id' => $supplierId,
            'discrepancies' => [
                'paid_in_erp_pending_in_dromega' => $paidInErpPendingInDromega,
                'pending_in_erp_paid_in_dromega' => $pendingInErpPaidInDromega,
                'total_discrepancies' => count($paidInErpPendingInDromega) + count($pendingInErpPaidInDromega),
            ],
            'details' => $processed,
        ];
    }

    /**
     * Reporta y procesa un pago directamente en el portal web de Droguería Mega.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank = 'C1051',
        ?string $paymentDate = null,
        ?string $photoUrl = null
    ): array {
        Log::info('[DROMEGA PAYMENT] Iniciando reporte de pago para facturas: ' . implode(', ', $invoiceNumbers));

        $cookieString = env('DROMEGA_COOKIE', 'wordpress_test_cookie=WP%20Cookie%20check; wp_lang=es_ES; wordpress_logged_in_39574764368bb892fdea55c61228e833=Farmacia_Barrio_Sucre%7C1789522005%7CYWx0d9WkwLcNilkn5JDCcVxXwC4xCWiXdW5dXvzvmCb%7Cd8a89bfde4906ecd86eabc0061b580cce09bb1b71de7a7f85fe54ec1657bed9d; _ga=GA1.1.786654209.1780670257; _ga_J50XJCL6NJ=GS2.1.s1780670257$o1$g0$t1780670272$j45$l0$h0; PHPSESSID=394ae3b6804e7d2b6e052a44b2cdd93d');

        // 1. Obtener estado de cuenta para extraer montos exactos de las facturas
        $extractedInvoices = $this->fetchInvoices($cookieString);
        $targetInvoices = [];
        $cleanTargets = array_map(fn($n) => ltrim((string) $n, '0'), $invoiceNumbers);

        foreach ($extractedInvoices as $inv) {
            $cleanNum = ltrim((string) $inv['num_factura'], '0');
            if (in_array($cleanNum, $cleanTargets) || in_array($inv['num_factura'], $invoiceNumbers)) {
                $targetInvoices[] = $inv;
            }
        }

        if (empty($targetInvoices)) {
            // Si no se encontraron por scraping, usar los números provistos
            foreach ($invoiceNumbers as $num) {
                $targetInvoices[] = [
                    'num_factura' => (string) $num,
                    'saldo_usd' => 0,
                    'saldo_bs' => $paymentAmount,
                ];
            }
        }

        // 2. Paso 1: Seleccionar facturas y enviar a /ventas/pago
        $totalBs = 0;
        $postStep1 = [
            'cliente' => '7586',
            'facturas' => [],
        ];

        foreach ($targetInvoices as $inv) {
            $docNum = (string) $inv['num_factura'];
            $postStep1['facturas'][] = $docNum;
            $postStep1["montopagar{$docNum}"] = number_format((float) ($inv['saldo_usd'] ?? 0), 2, ',', '.');
            $postStep1["montopagarbs{$docNum}"] = number_format((float) ($inv['saldo_bs'] ?? 0), 2, ',', '.');
            $totalBs += (float) ($inv['saldo_bs'] ?? 0);
        }

        $postStep1['total_por_pagar'] = number_format($totalBs > 0 ? $totalBs : $paymentAmount, 2, '.', '');

        $step1Res = $this->executeCurl(self::BASE_URL . '/ventas/pago', $cookieString, [
            'post' => $postStep1,
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . self::BASE_URL . '/ventas/cuentas-por-pagar/?sucursal=merida&cliente=7586',
            ],
        ]);

        if ($step1Res['status'] !== 200) {
            throw new \RuntimeException("Error en el paso 1 de pago en Droguería Mega (HTTP {$step1Res['status']}).");
        }

        // 3. Extraer el campo oculto 'facts' de la respuesta
        $factsVal = '';
        if (preg_match('/<input[^>]*name=["\']facts["\'][^>]*value=["\']([^"\']*)["\']/i', $step1Res['body'], $m)) {
            $factsVal = $m[1];
        }

        if (empty($factsVal)) {
            Log::warning('[DROMEGA PAYMENT] No se pudo extraer el campo "facts" del formulario de pago.');
        }

        // 4. Formatear los últimos 9 dígitos del número de operación
        $cleanRef = preg_replace('/\D/', '', $reference);
        $nroOperacion = strlen($cleanRef) >= 9 ? substr($cleanRef, -9) : $reference;

        // 5. Preparar comprobante adjunto si existe
        $curlFile = null;
        if (!empty($photoUrl)) {
            $relativePath = ltrim(str_replace(['/storage/', 'storage/'], '', $photoUrl), '/\\');
            $fullPath = storage_path('app/public/' . $relativePath);

            if (!file_exists($fullPath)) {
                $fullPath = public_path('storage/' . $relativePath);
            }

            if (file_exists($fullPath)) {
                $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
                $curlFile = new \CURLFile($fullPath, $mimeType, basename($fullPath));
            }
        }

        // 6. Paso 2: Enviar reporte de pago con multipart/form-data
        $postStep2 = [
            'datos_pago' => '1',
            'cliente' => '7586',
            'facts' => $factsVal,
            'fecha1' => $paymentDate ?: Carbon::today()->format('Y-m-d'),
            'tipo1' => 'transferencia',
            'monto1' => number_format($paymentAmount, 2, ',', '.'),
            'banco1' => $destinationBank ?: 'C1051',
            'nro_operacion1' => $nroOperacion,
            'nro_pagos' => '1',
            'cantidad_retenciones' => '0',
        ];

        if ($curlFile) {
            $postStep2['comprobante_pago1'] = $curlFile;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::BASE_URL . '/ventas/pago',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postStep2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                "Cookie: {$cookieString}",
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Referer: ' . self::BASE_URL . '/ventas/pago',
            ],
        ]);
        $step2Output = curl_exec($ch);
        $step2Status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isSuccess = ($step2Status === 200 || $step2Status === 302);

        Log::info("[DROMEGA PAYMENT] Reporte de pago finalizado con status HTTP {$step2Status}. Referencia: {$nroOperacion}, Facturas: " . implode(', ', $invoiceNumbers));

        return [
            'success' => $isSuccess,
            'status' => $step2Status,
            'reference' => $nroOperacion,
            'invoices' => $invoiceNumbers,
            'amount_paid' => $paymentAmount,
            'bank_code' => $destinationBank,
        ];
    }

    /**
     * Extrae el detalle de renglones/productos de una factura desde el portal de Droguería Mega.
     */
    public function fetchInvoiceDetail(string $invoiceNumber, ?string $cookie = null): ?array
    {
        $cookieString = $cookie ?: env('DROMEGA_COOKIE', 'wordpress_test_cookie=WP%20Cookie%20check; wp_lang=es_ES; wordpress_logged_in_39574764368bb892fdea55c61228e833=Farmacia_Barrio_Sucre%7C1789522005%7CYWx0d9WkwLcNilkn5JDCcVxXwC4xCWiXdW5dXvzvmCb%7Cd8a89bfde4906ecd86eabc0061b580cce09bb1b71de7a7f85fe54ec1657bed9d; _ga=GA1.1.786654209.1780670257; _ga_J50XJCL6NJ=GS2.1.s1780670257$o1$g0$t1780670272$j45$l0$h0; PHPSESSID=394ae3b6804e7d2b6e052a44b2cdd93d');

        $url = self::BASE_URL . "/ventas/datos-factura/?factura={$invoiceNumber}";
        $res = $this->executeCurl($url, $cookieString, [
            'headers' => [
                'Referer: ' . self::ESTADO_CUENTA_URL,
            ],
        ]);

        if ($res['status'] !== 200 || empty($res['body'])) {
            Log::warning("[DROMEGA SCRAPER] No se pudo obtener detalle para factura #{$invoiceNumber}");
            return null;
        }

        $html = $res['body'];
        $detail = [
            'nroFactura' => $invoiceNumber,
            'fecha' => '',
            'tasaCambio' => 0,
            'operadorVentas' => 'Ventas 3',
            'telefonoOperador' => '0414-7546671',
            'operadorCobranza' => 'Yelitza Dávila',
            'descCliente' => 'FARMACIA BARRIO SUCRE 2024, C.A',
            'rifCliente' => 'J-50540695-7',
            'codCliente' => '7586',
            'items' => [],
            'totales' => [
                'subtotal_bs' => '0,00',
                'subtotal_usd' => '0,00',
                'descuento_bs' => '0,00',
                'descuento_usd' => '0,00',
                'iva_bs' => '0,00',
                'iva_usd' => '0,00',
                'total_bs' => '0,00',
                'total_usd' => '0,00',
            ],
        ];

        if (preg_match('/Fecha:\s*([\d\/]+)/i', $html, $m)) {
            $detail['fecha'] = trim($m[1]);
        }
        if (preg_match('/Tasa de Cambio:\s*([\d\.,]+)/i', $html, $m)) {
            $detail['tasaCambio'] = (float) str_replace(',', '.', trim($m[1]));
        }

        // Extraer tablas de productos y totales
        preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tables);

        foreach ($tables[0] as $tableHtml) {
            if (str_contains($tableHtml, 'Código Barras') && str_contains($tableHtml, 'Descripción')) {
                preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows);
                for ($rIdx = 1; $rIdx < count($rows[0]); $rIdx++) {
                    preg_match_all('/<(?:td|th)[^>]*>(.*?)<\/(?:td|th)>/is', $rows[0][$rIdx], $cols);
                    $cTexts = array_map(fn($c) => trim(strip_tags($c)), $cols[1] ?? []);
                    if (count($cTexts) >= 8) {
                        $detail['items'][] = [
                            'codigo' => $cTexts[0],
                            'codigo_barras' => $cTexts[1],
                            'descripcion' => $cTexts[2],
                            'cantidad' => (int) $cTexts[3],
                            'precio_unitario' => $cTexts[4],
                            'descuento' => $cTexts[5],
                            'total_bs' => $cTexts[6],
                            'total_usd' => $cTexts[7],
                        ];
                    }
                }
            } elseif (str_contains($tableHtml, 'Sub Total') || str_contains($tableHtml, 'IVA')) {
                preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows);
                foreach ($rows[0] as $totRow) {
                    preg_match_all('/<(?:td|th)[^>]*>(.*?)<\/(?:td|th)>/is', $totRow, $cols);
                    $cTexts = array_map(fn($c) => trim(strip_tags($c)), $cols[1] ?? []);
                    if (count($cTexts) >= 3) {
                        $label = strtolower($cTexts[0]);
                        if (str_contains($label, 'sub total') || str_contains($label, 'subtotal')) {
                            $detail['totales']['subtotal_bs'] = $cTexts[1];
                            $detail['totales']['subtotal_usd'] = $cTexts[2];
                        } elseif (str_contains($label, 'descuento')) {
                            $detail['totales']['descuento_bs'] = $cTexts[1];
                            $detail['totales']['descuento_usd'] = $cTexts[2];
                        } elseif (str_contains($label, 'iva')) {
                            $detail['totales']['iva_bs'] = $cTexts[1];
                            $detail['totales']['iva_usd'] = $cTexts[2];
                        } elseif (str_contains($label, 'total')) {
                            $detail['totales']['total_bs'] = $cTexts[1];
                            $detail['totales']['total_usd'] = $cTexts[2];
                        }
                    }
                }
            }
        }

        return $detail;
    }

    /**
     * Genera y almacena el archivo PDF digital de la factura para visualizarla en el ERP.
     */
    public function generateAndStoreInvoicePdf(Invoice $invoice, ?array $detailData = null): ?string
    {
        try {
            $invoiceNumber = (string) $invoice->invoice_number;
            $detail = $detailData ?: $this->fetchInvoiceDetail($invoiceNumber);

            if (!$detail) {
                $detail = [
                    'nroFactura' => $invoiceNumber,
                    'fecha' => $invoice->created_invoice_date ? $invoice->created_invoice_date->format('d/m/Y') : date('d/m/Y'),
                    'tasaCambio' => (float) ($invoice->exchange_rate ?? 1),
                    'items' => [],
                    'totales' => [
                        'subtotal_bs' => number_format((float) $invoice->total_amount, 2, ',', '.'),
                        'subtotal_usd' => number_format((float) $invoice->total_usd, 2, ',', '.'),
                        'descuento_bs' => '0,00',
                        'descuento_usd' => '0,00',
                        'iva_bs' => number_format((float) $invoice->tax_amount, 2, ',', '.'),
                        'iva_usd' => '0,00',
                        'total_bs' => number_format((float) ($invoice->net_payable_amount ?: $invoice->total_amount), 2, ',', '.'),
                        'total_usd' => number_format((float) $invoice->total_usd, 2, ',', '.'),
                    ],
                ];
            }

            if (!empty($invoice->invoice_photo) && Storage::disk('public')->exists($invoice->invoice_photo)) {
                Storage::disk('public')->delete($invoice->invoice_photo);
            }

            $pdf = Pdf::loadView('pdf.dromega_invoice', [
                'invoice' => $invoice,
                'detail' => $detail,
            ])->setPaper('letter', 'portrait');

            $pdfFileName = "invoice_dromega_{$invoiceNumber}_" . time() . ".pdf";
            $pdfStorageRelPath = "invoices/{$pdfFileName}";

            Storage::disk('public')->put($pdfStorageRelPath, $pdf->output());

            $invoice->update(['invoice_photo' => $pdfStorageRelPath]);

            return $pdfStorageRelPath;
        } catch (\Throwable $e) {
            Log::warning("[DROMEGA SCRAPER] Error generando PDF para factura #{$invoice->invoice_number}: " . $e->getMessage());
            return null;
        }
    }
}
