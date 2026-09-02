<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DrosymcaScraperServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class DrosymcaScraperService implements DrosymcaScraperServiceInterface
{
    private const BASE_URL = 'https://app.drosymca.com';
    private const LOGIN_URL = 'https://app.drosymca.com/login';
    private const COBRANZA_URL = 'https://app.drosymca.com/cobranza';
    private const FACTURAS_URL = 'https://app.drosymca.com/facturas';

    /**
     * Ejecuta una petición HTTP con soporte de cookies y cabeceras estándar.
     */
    private function executeCurl(string $method, string $url, string $cookieFile, array $options = []): array
    {
        $headers = array_merge([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        ], $options['headers'] ?? []);

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
                CURLOPT_TIMEOUT => 35,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => $curlHeaders,
            ];

            if (strtoupper($method) === 'POST') {
                $curlOpts[CURLOPT_POST] = true;
                if (isset($options['json'])) {
                    $curlOpts[CURLOPT_POSTFIELDS] = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $curlHeaders[] = 'Content-Type: application/json';
                    $curlOpts[CURLOPT_HTTPHEADER] = $curlHeaders;
                } elseif (isset($options['form_params'])) {
                    $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($options['form_params']);
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

        return ['status' => 0, 'header' => '', 'body' => ''];
    }

    /**
     * Inicia sesión en el portal web de Drosymca y retorna la ruta del archivo de cookies y token CSRF.
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
            $cookieFile = $cookieDir . DIRECTORY_SEPARATOR . 'drosymca_' . md5($username . microtime()) . '.txt';

            try {
                // 1. Obtener CSRF token inicial desde el formulario de login
                $resLogin = $this->executeCurl('GET', self::LOGIN_URL, $cookieFile);

                $csrfToken = null;
                if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $resLogin['body'], $m)) {
                    $csrfToken = $m[1];
                } elseif (preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/i', $resLogin['body'], $m)) {
                    $csrfToken = $m[1];
                }

                if (!$csrfToken) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException('No se pudo obtener el token CSRF de la página de inicio de sesión de Drosymca.');
                }

                // 2. Enviar credenciales vía POST JSON (formato Laravel / Axios)
                $loginPost = $this->executeCurl('POST', self::LOGIN_URL, $cookieFile, [
                    'headers' => [
                        'X-CSRF-TOKEN' => $csrfToken,
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Accept' => 'application/json, text/plain, */*',
                        'Referer' => self::LOGIN_URL,
                        'Origin' => self::BASE_URL,
                    ],
                    'json' => [
                        'email' => $username,
                        'password' => $password,
                    ],
                ]);

                if ($loginPost['status'] >= 400) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException("Fallo la autenticación en Drosymca con el usuario '{$username}'. Estado HTTP: {$loginPost['status']}");
                }

                // 3. Probar acceso al módulo de cobranza
                $resCobranza = $this->executeCurl('GET', self::COBRANZA_URL, $cookieFile, [
                    'headers' => [
                        'Referer' => self::LOGIN_URL,
                    ],
                ]);

                if (str_contains($resCobranza['body'], 'login-form') || str_contains($resCobranza['body'], 'INGRESAR')) {
                    if (file_exists($cookieFile)) {
                        @unlink($cookieFile);
                    }
                    throw new \RuntimeException("Las credenciales para Drosymca ('{$username}') fueron rechazadas o la sesión expiró.");
                }

                return [
                    'cookie_file' => $cookieFile,
                    'csrf_token' => $csrfToken,
                    'cobranza_html' => $resCobranza['body'],
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

        throw ($lastException ?: new \RuntimeException('Error al iniciar sesión en el portal Drosymca tras varios intentos.'));
    }

    /**
     * Extrae el listado de facturas pendientes de la sección Cobranza de Drosymca.
     */
    public function fetchPendingInvoices(string $username, string $password): array
    {
        $session = $this->createAuthenticatedSession($username, $password);
        $html = $session['cobranza_html'];
        $cookieFile = $session['cookie_file'];

        try {
            $invoices = $this->parseCobranzaHtml($html);

            // Para cada factura, si se requiere detalle adicional, se puede consultar con la sesión activa
            foreach ($invoices as &$inv) {
                if (!empty($inv['portal_id'])) {
                    $detail = $this->fetchInvoiceDetail($inv['portal_id'], $cookieFile);
                    if ($detail) {
                        $inv['detail'] = $detail;
                        if (isset($detail['exempt_amount'])) {
                            $inv['exempt_amount'] = $detail['exempt_amount'];
                        }
                        if (isset($detail['taxable_base'])) {
                            $inv['taxable_base'] = $detail['taxable_base'];
                        }
                        if (isset($detail['tax_amount'])) {
                            $inv['tax_amount'] = $detail['tax_amount'];
                        }
                    }
                }
            }
            unset($inv);

            return $invoices;
        } finally {
            if (file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    /**
     * Parsea el HTML de la tabla de cobranzas de Drosymca.
     */
    private function parseCobranzaHtml(string $html): array
    {
        $invoices = [];
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//table[contains(@class, "table-report")]//tbody/tr');
        $today = Carbon::now()->format('Y-m-d');

        foreach ($rows as $tr) {
            $tds = $xpath->query('.//td', $tr);
            if ($tds->length < 6) {
                continue;
            }

            $rawDocNum = trim($tds->item(0)->textContent);
            $rawDate = trim($tds->item(1)->textContent);
            $rawDueDate = trim($tds->item(2)->textContent);
            $rawDays = trim($tds->item(3)->textContent);
            $rawTotal = trim($tds->item(4)->textContent);
            $rawBalance = trim($tds->item(5)->textContent);

            $detailLinkNode = $xpath->query('.//a[contains(@href, "/facturas/")]', $tds->item(6));
            $detailUrl = null;
            $portalId = null;
            if ($detailLinkNode->length > 0) {
                $href = $detailLinkNode->item(0)->getAttribute('href');
                $detailUrl = self::BASE_URL . $href;
                if (preg_match('/\/facturas\/(\d+)/', $href, $m)) {
                    $portalId = $m[1];
                }
            }

            $issueDate = null;
            if (!empty($rawDate)) {
                try {
                    $cleanDate = preg_replace('/[^\d\-]/', '', $rawDate);
                    $issueDate = Carbon::createFromFormat('d-m-Y', $cleanDate)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $issueDate = null;
                }
            }

            $dueDate = null;
            if (!empty($rawDueDate)) {
                try {
                    $cleanDueDate = preg_replace('/[^\d\-]/', '', $rawDueDate);
                    $dueDate = Carbon::createFromFormat('d-m-Y', $cleanDueDate)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $dueDate = null;
                }
            }

            $totalAmount = $this->parseAmount($rawTotal);
            $balance = $this->parseAmount($rawBalance);

            // Si la factura vence hoy, se mantiene NO indexada (hoy es el último día vigente).
            // Pasa a indexada únicamente si la fecha de vencimiento ya pasó (ayer o antes).
            $isOverdue = str_contains(strtolower($rawDays), 'vencid') && ($dueDate && $dueDate < $today);
            $isIndexed = ($dueDate && $dueDate < $today) || $isOverdue;

            $invoices[] = [
                'document_number' => $rawDocNum,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'days_text' => $rawDays,
                'is_overdue' => $isOverdue,
                'is_indexed' => $isIndexed,
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'portal_id' => $portalId,
                'detail_url' => $detailUrl,
            ];
        }

        return $invoices;
    }

    /**
     * Extrae el desglose de una factura desde /facturas/{id}.
     */
    public function fetchInvoiceDetail(string $invoiceUrlOrId, ?string $cookieFile = null, ?string $username = null, ?string $password = null): ?array
    {
        $shouldCleanCookie = false;
        if (!$cookieFile) {
            $user = $username ?: env('DROSYMCA_USERNAME', 'farmab.sucre2024@gmail.com');
            $pass = $password ?: env('DROSYMCA_PASSWORD', 'J505406957');
            $session = $this->createAuthenticatedSession($user, $pass);
            $cookieFile = $session['cookie_file'];
            $shouldCleanCookie = true;
        }

        try {
            $url = str_starts_with($invoiceUrlOrId, 'http') ? $invoiceUrlOrId : self::FACTURAS_URL . '/' . $invoiceUrlOrId;
            $res = $this->executeCurl('GET', $url, $cookieFile, [
                'headers' => ['Referer' => self::COBRANZA_URL],
            ]);

            if ($res['status'] !== 200 || empty($res['body'])) {
                return null;
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($res['body']);
            $xpath = new DOMXPath($dom);

            $detailBoxes = $xpath->query('//div[contains(@class, "box")]');
            $details = [
                'exempt_amount' => 0.0,
                'taxable_base' => 0.0,
                'tax_amount' => 0.0,
                'discount_amount' => 0.0,
                'total_amount' => 0.0,
            ];

            foreach ($detailBoxes as $box) {
                $text = $box->textContent;
                if (str_contains($text, 'Detalle Total')) {
                    $lines = $xpath->query('.//div[contains(@class, "flex items-center")]', $box);
                    foreach ($lines as $line) {
                        $lineText = trim($line->textContent);
                        if (preg_match('/Exento:\s*([^\n]+)/i', $lineText, $m)) {
                            $details['exempt_amount'] = $this->parseAmount($m[1]);
                        }
                        if (preg_match('/Base:\s*([^\n]+)/i', $lineText, $m)) {
                            $details['taxable_base'] = $this->parseAmount($m[1]);
                        }
                        if (preg_match('/IVA:\s*([^\n]+)/i', $lineText, $m)) {
                            $details['tax_amount'] = $this->parseAmount($m[1]);
                        }
                        if (preg_match('/DESCUENTO:\s*([^\n]+)/i', $lineText, $m)) {
                            $details['discount_amount'] = $this->parseAmount($m[1]);
                        }
                        if (preg_match('/TOTAL:\s*([^\n]+)/i', $lineText, $m)) {
                            $details['total_amount'] = $this->parseAmount($m[1]);
                        }
                    }
                }
            }

            return $details;
        } finally {
            if ($shouldCleanCookie && file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    /**
     * Limpia y convierte cadenas numéricas con formato a float.
     */
    private function parseAmount(string $val): float
    {
        $clean = trim(preg_replace('/[^\d,\.]/', '', $val));
        if ($clean === '') {
            return 0.0;
        }

        if (str_contains($clean, '.') && str_contains($clean, ',')) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                // Formato latinoamericano/europeo 52.699,01
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // Formato US 52,699.01
                $clean = str_replace(',', '', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        return (float) $clean;
    }

    /**
     * Sincroniza las facturas de Drosymca con el ERP:
     * - Detecta fechas de vencimiento reales.
     * - Marca facturas como indexadas cuando vencen (exp_date <= today).
     * - Actualiza saldos y montos fiscales.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array
    {
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%DROSYM%')
                ->orWhere('name', 'LIKE', '%DROSI%')
                ->orWhere('id', 1006)
                ->first();
            $supplierId = $supplier?->id ?? 1006;
        }

        // Obtener conexión configurada en la BD si existe
        $conn = $supplier?->connections?->where('type', 'drosymca_bot')->first()
            ?? $supplier?->connections?->first();

        $user = $username;
        $pass = $password;

        if (!$user && $conn && !empty($conn->username)) {
            $user = $conn->username;
        }
        if (!$pass && $conn && !empty($conn->password)) {
            $pass = FtpCrypt::decrypt($conn->password);
        }

        $user = $user ?: env('DROSYMCA_USERNAME', 'farmab.sucre2024@gmail.com');
        $pass = $pass ?: env('DROSYMCA_PASSWORD', 'J505406957');

        $documents = $this->fetchPendingInvoices($user, $pass);

        if (empty($documents)) {
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
            $targetClean = ltrim((string) $onlyInvoice, '0');
            $documents = array_values(array_filter($documents, function ($d) use ($targetClean, $onlyInvoice) {
                $docClean = ltrim((string) $d['document_number'], '0');
                return $docClean === $targetClean || $d['document_number'] === $onlyInvoice;
            }));
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $processed = [];
        $today = Carbon::now()->format('Y-m-d');
        $userId = User::first()?->id ?? 1;

        // Obtener tasa de cambio actual para facturas nuevas
        $currentExchangeRate = (float) (ExchangeRate::latest('id')->value('rate') ?? 0);

        foreach ($documents as $doc) {
            $rawDocNum = (string) ($doc['document_number'] ?? '');
            $cleanNumber = ltrim($rawDocNum, '0');

            if (empty($cleanNumber)) {
                $skippedCount++;
                continue;
            }

            $expDate = !empty($doc['due_date']) ? Carbon::parse($doc['due_date'])->format('Y-m-d') : null;
            $emisionDate = !empty($doc['issue_date']) ? Carbon::parse($doc['issue_date'])->format('Y-m-d') : null;

            // Factura indexada SOLO si la fecha de vencimiento ya pasó (ayer o antes). Si vence hoy, es el último día no indexada.
            $isIndexed = !empty($expDate) && ($expDate < $today);

            // Múltiples formatos posibles para buscar la factura en el ERP
            $possibleNumbers = array_unique([
                $rawDocNum,
                $cleanNumber,
                'FAC-' . $rawDocNum,
                'FAC-' . $cleanNumber,
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
            ]);

            $invoice = Invoice::where('supplier_id', $supplierId)
                ->where(function ($query) use ($possibleNumbers) {
                    $query->whereIn('invoice_number', $possibleNumbers);
                })
                ->first();

            $totalAmount = (float) ($doc['total_amount'] ?? 0);
            $exemptAmount = (float) ($doc['exempt_amount'] ?? $totalAmount);
            $taxableBase = (float) ($doc['taxable_base'] ?? 0);
            $taxAmount = (float) ($doc['tax_amount'] ?? 0);

            if ($invoice) {
                // Actualizar factura existente sin alterar status ni status_payment
                $updateData = [];

                if ($expDate) {
                    $updateData['exp_date'] = $expDate;
                    if ((int) ($invoice->status_payment ?? 0) !== 1) {
                        $updateData['payment_date'] = $expDate;
                    }
                }

                if ($emisionDate && empty($invoice->created_invoice_date)) {
                    $updateData['created_invoice_date'] = $emisionDate;
                }

                $updateData['is_indexed'] = $isIndexed;

                if ($totalAmount > 0) {
                    $updateData['total_amount'] = $totalAmount;
                    $updateData['exempt_amount'] = $exemptAmount;
                    $updateData['taxable_base'] = $taxableBase;
                    $updateData['tax_amount'] = $taxAmount;

                    if ($invoice->exchange_rate && (float) $invoice->exchange_rate > 0) {
                        $updateData['total_usd'] = round($totalAmount / (float) $invoice->exchange_rate, 2);
                    } elseif ($currentExchangeRate > 0) {
                        $updateData['exchange_rate'] = $currentExchangeRate;
                        $updateData['total_usd'] = round($totalAmount / $currentExchangeRate, 2);
                    }
                }

                $invoice->update($updateData);
                $updatedCount++;

                $processed[] = [
                    'action' => 'updated',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_amount' => $totalAmount,
                ];
            } else {
                // Registrar nueva factura pendiente desde el portal
                $totalUsd = ($currentExchangeRate > 0) ? round($totalAmount / $currentExchangeRate, 2) : 0.00;

                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'invoice_number' => $rawDocNum,
                    'control_number' => null,
                    'exp_date' => $expDate ?: $today,
                    'payment_date' => $expDate ?: $today,
                    'received_date' => $emisionDate ?: $today,
                    'created_invoice_date' => $emisionDate ?: $today,
                    'currency' => 'Bs',
                    'is_indexed' => $isIndexed,
                    'exempt_amount' => $exemptAmount,
                    'taxable_base' => $taxableBase,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'exchange_rate' => $currentExchangeRate,
                    'total_usd' => $totalUsd,
                    'status' => 'ordered',
                    'status_payment' => 0,
                    'loaded_by' => $userId,
                    'registered_by' => $userId,
                    'uploaded_by' => $userId,
                ]);

                $createdCount++;

                $processed[] = [
                    'action' => 'created',
                    'invoice_id' => $newInvoice->id,
                    'invoice_number' => $newInvoice->invoice_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_amount' => $totalAmount,
                ];
            }
        }

        return [
            'total_extracted' => count($documents),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'details' => $processed,
        ];
    }
}
