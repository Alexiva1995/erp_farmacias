<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DrocercaScraperServiceInterface;
use App\Models\Invoice;
use App\Models\Supplier;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class DrocercaScraperService implements DrocercaScraperServiceInterface
{
    private const BASE_URL = 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli';
    private const LOGIN_URL = 'http://drocerca.proteoerp.org:8082/proteoerp/bienvenido/autenticli';
    private const FACTURA_URL = 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli/facturavend';
    private const PAGOS_URL = 'http://drocerca.proteoerp.org:8082/proteoerp/portalcli/pagos';

    /**
     * Sincroniza las facturas de Drocerca en la tabla invoices.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array
    {
        @ini_set('memory_limit', '512M');
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%DROCERCA%')
                ->orWhere('name', 'LIKE', '%CERCA%')
                ->first();
            if (!$supplier) {
                $supplier = Supplier::firstOrCreate(
                    ['name' => 'DROGUERIA CERCA (DROCERCA)'],
                    [
                        'rif' => 'J-50540695-7',
                        'dispatch_days' => [],
                        'order_days' => [],
                        'type' => \App\Enums\SupplierType::DROGUERIA,
                        'is_active' => true,
                        'rating' => 5,
                    ]
                );
            }
            $supplierId = $supplier?->id;
        }

        // Obtener conexión configurada en la BD específicamente para el bot de Drocerca
        $conn = $supplier?->connections?->where('type', 'drocerca_bot')->first();

        $user = $username;
        $pass = $password;

        if (!$user && $conn && !empty($conn->username)) {
            $user = $conn->username;
        }
        if (!$pass && $conn && !empty($conn->password)) {
            $pass = \App\Helpers\FtpCrypt::decrypt($conn->password);
        }

        // Fallbacks por defecto para el portal de Drocerca
        $user = $user ?: env('DROCERCA_USERNAME', 'W008B3');
        $pass = $pass ?: env('DROCERCA_PASSWORD', 'J505406957');

        // 1. Obtener listado de facturas emitidas desde la sección Facturación
        $documents = $this->fetchDocuments($user, $pass);

        // 2. Obtener estado de cuenta y efectos por pagar en las 3 sedes (Mérida, Centro, Oriente)
        $edoCuentaMap = $this->fetchEdoCuenta($user, $pass);

        if (empty($documents) && empty($edoCuentaMap)) {
            return [
                'total_extracted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'created' => 0,
                'supplier_id' => $supplierId,
                'details' => [],
            ];
        }

        // Si se especificó una factura única (ej. 00909968 o ID)
        if ($onlyInvoice) {
            $targetNum = $onlyInvoice;
            if (is_numeric($onlyInvoice) && intval($onlyInvoice) < 100000) {
                $invById = Invoice::find((int)$onlyInvoice);
                if ($invById) {
                    $targetNum = $invById->invoice_number;
                }
            }
            $cleanTarget = ltrim($targetNum, '0');
            $documents = array_values(array_filter($documents, function ($d) use ($cleanTarget, $targetNum) {
                $docClean = ltrim($d['documento'], '0');
                return $docClean === $cleanTarget || $d['documento'] === $targetNum;
            }));
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $processed = [];

        $client = new Client(['verify' => false, 'timeout' => 30]);
        $today = Carbon::now()->format('Y-m-d');

        foreach ($documents as $doc) {
            $docNumber = $doc['documento'];
            $controlNumber = $doc['nro_control'];
            $pdfUrl = $doc['pdf_url'];
            $totalBsPortal = $doc['total_bs'];
            $totalUsdPortal = $doc['total_usd'];
            $fechaEmisionPortal = $doc['fecha'];

            if (empty($docNumber)) {
                $skippedCount++;
                continue;
            }

            // Descargar y parsear el PDF de NovusFactura / Drocerca para obtener fecha de vencimiento, exento, base, iva, tasa
            $pdfData = null;
            if (!empty($pdfUrl)) {
                $pdfData = $this->fetchAndParsePdf($pdfUrl, $client);
            }

            $expDate = $pdfData['payment_due_date'] ?? $pdfData['created_invoice_date'] ?? $fechaEmisionPortal;
            $createdDate = $pdfData['created_invoice_date'] ?? $fechaEmisionPortal;
            $exchangeRate = $pdfData['exchange_rate'] ?? ($totalUsdPortal > 0 ? round($totalBsPortal / $totalUsdPortal, 2) : 0);
            $exemptAmount = $pdfData['exempt_amount'] ?? 0;
            $taxableBase = $pdfData['taxable_base'] ?? 0;
            $taxAmount = $pdfData['tax_amount'] ?? 0;
            $totalAmount = $pdfData['total_amount'] ?? $totalBsPortal;
            $totalUsd = $pdfData['total_usd'] ?? $totalUsdPortal;
            $invoicePhoto = $pdfData['invoice_photo'] ?? null;
            $finalControlNumber = $pdfData['control_number'] ?? $controlNumber;

            // Determinar si la factura está INDEXADA según Edo. Cuenta en cualquiera de las 3 sedes:
            // Se busca por número de factura (con o sin FC o ceros a la izquierda)
            $cleanDocKey = ltrim($docNumber, '0');
            $edoItem = $edoCuentaMap[$cleanDocKey] ?? $edoCuentaMap[$docNumber] ?? null;

            $isIndexed = false;
            if ($edoItem) {
                $fIndexa = $edoItem['f_indexa_db']; // formato Y-m-d
                // Si la fecha de indexación (F.Indexa) es igual o menor a hoy (ya pasó o es hoy), está indexada
                if (!empty($fIndexa) && $fIndexa <= $today) {
                    $isIndexed = true;
                }
                if (!empty($edoItem['fecha_vencimiento_db'])) {
                    $expDate = $edoItem['fecha_vencimiento_db'];
                }
            } else {
                // Si no está en efectos por pagar del portal o ya venció
                if ($expDate && $expDate < $today) {
                    $isIndexed = true;
                }
            }

            // Buscar si ya existe la factura registrada por número de documento (con prefijos F, FC, FA, etc.) o control
            $digitsOnly = preg_replace('/\D/', '', $docNumber);
            $cleanNumber = ltrim($digitsOnly ?: $docNumber, '0');
            $paddedNumber = str_pad($cleanNumber, 8, '0', STR_PAD_LEFT);

            $possibleNumbers = array_unique(array_filter([
                $docNumber,
                $cleanNumber,
                $paddedNumber,
                'F' . $cleanNumber,
                'F' . $paddedNumber,
                'FC' . $cleanNumber,
                'FC' . $paddedNumber,
                'FA' . $cleanNumber,
                'FA' . $paddedNumber,
                'F' . $docNumber,
                'FC' . $docNumber,
                'FA' . $docNumber,
            ]));

            $invoiceQuery = Invoice::where(function ($q) use ($possibleNumbers, $cleanNumber, $finalControlNumber) {
                $q->whereIn('invoice_number', $possibleNumbers);
                if (!empty($cleanNumber) && strlen($cleanNumber) >= 4) {
                    $q->orWhere('invoice_number', 'LIKE', "%{$cleanNumber}");
                }
                if (!empty($finalControlNumber) && $finalControlNumber !== 'N/A') {
                    $q->orWhere('control_number', $finalControlNumber);
                }
            });

            if ($supplierId) {
                $invoiceQuery->where('supplier_id', $supplierId);
            }

            $matchingInvoices = $invoiceQuery->orderByDesc('id')->get();
            $invoice = $matchingInvoices->first();

            // Si existen duplicados (ej. F00909968 y 00909968), limpiar los duplicados vacíos
            if ($matchingInvoices->count() > 1) {
                $primaryInvoice = $matchingInvoices->firstWhere(fn($i) => !empty($i->control_number) && $i->control_number !== 'N/A') 
                    ?? $matchingInvoices->first();
                foreach ($matchingInvoices as $dup) {
                    if ($dup->id !== $primaryInvoice->id && $dup->status_payment == 0) {
                        $dup->delete();
                    }
                }
                $invoice = $primaryInvoice;
            }

            // Número oficial con prefijo según el portal (ej. FC00909968 de Edo. Cuenta o F00909968)
            $officialInvoiceNumber = $edoItem['numero_raw'] ?? (!empty($pdfData['invoice_number']) ? $pdfData['invoice_number'] : $docNumber);

            if ($invoice) {
                $updateData = [
                    'invoice_number' => $officialInvoiceNumber,
                    'control_number' => $finalControlNumber ?: $invoice->control_number,
                    'exp_date' => $expDate ?: $invoice->exp_date,
                    'payment_date' => $expDate ?: $invoice->payment_date,
                    'is_indexed' => $isIndexed,
                ];

                if (!empty($invoicePhoto) && empty($invoice->invoice_photo)) {
                    $updateData['invoice_photo'] = $invoicePhoto;
                }
                if (floatval($exchangeRate) > 0 && floatval($invoice->exchange_rate ?? 0) <= 0) {
                    $updateData['exchange_rate'] = $exchangeRate;
                }
                if (floatval($exemptAmount) > 0 && floatval($invoice->exempt_amount ?? 0) <= 0) {
                    $updateData['exempt_amount'] = $exemptAmount;
                }
                if (floatval($taxableBase) > 0 && floatval($invoice->taxable_base ?? 0) <= 0) {
                    $updateData['taxable_base'] = $taxableBase;
                }
                if (floatval($taxAmount) > 0 && floatval($invoice->tax_amount ?? 0) <= 0) {
                    $updateData['tax_amount'] = $taxAmount;
                }
                if (floatval($totalAmount) > 0) {
                    $updateData['total_amount'] = $totalAmount;
                }
                if (floatval($totalUsd) > 0) {
                    $updateData['total_usd'] = $totalUsd;
                }

                $invoice->update($updateData);
                $updatedCount++;
                $processed[] = [
                    'invoice_number' => $invoice->invoice_number,
                    'action' => 'updated',
                    'control_number' => $invoice->control_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_amount' => $invoice->total_amount,
                    'total_usd' => $invoice->total_usd,
                ];
            } else {
                $userId = \Illuminate\Support\Facades\Auth::id() ?? \App\Models\User::first()?->id ?? 1;

                // Crear factura si no existía en el ERP
                // Solo se marca como pendiente (status_payment = 0) si figura en Efectos por Pagar (Edo. Cuenta)
                $isPendingInDrocerca = !empty($edoItem);

                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'invoice_number' => $officialInvoiceNumber,
                    'control_number' => $finalControlNumber,
                    'created_invoice_date' => $createdDate,
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'exchange_rate' => $exchangeRate,
                    'exempt_amount' => $exemptAmount,
                    'taxable_base' => $taxableBase,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'total_usd' => $totalUsd,
                    'is_indexed' => $isIndexed,
                    'status' => $isPendingInDrocerca ? 'pending' : 'paid',
                    'status_payment' => $isPendingInDrocerca ? 0 : 1,
                    'uploaded_by' => $userId,
                    'registered_by' => $userId,
                    'invoice_photo' => $invoicePhoto,
                ]);

                $createdCount++;
                $processed[] = [
                    'invoice_number' => $newInvoice->invoice_number,
                    'action' => 'created',
                    'control_number' => $newInvoice->control_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_amount' => $newInvoice->total_amount,
                    'total_usd' => $newInvoice->total_usd,
                ];
            }
        }

        return [
            'total_extracted' => count($documents),
            'updated' => $updatedCount,
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'details' => $processed,
        ];
    }

    /**
     * Extrae el listado de facturas directamente desde el portal web de Drocerca.
     */
    public function fetchDocuments(string $username, string $password): array
    {
        $client = new Client([
            'verify' => false,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]
        ]);

        try {
            // 1. Iniciar sesión en ProteoERP
            $loginRes = $client->post(self::LOGIN_URL, [
                'form_params' => [
                    'user' => $username,
                    'pws' => $password,
                ],
                'headers' => [
                    'Referer' => self::BASE_URL,
                ],
                'allow_redirects' => false
            ]);

            $setCookies = $loginRes->getHeader('Set-Cookie');
            $cookieHeader = '';
            foreach ($setCookies as $sc) {
                $parts = explode(';', $sc);
                $cookieHeader .= trim($parts[0]) . '; ';
            }

            if (empty($cookieHeader)) {
                Log::warning('[DrocercaScraper] No se recibieron cookies de sesión durante el login.');
                return [];
            }

            // 2. Consultar tabla de Facturación (facturavend)
            $resFac = $client->post(self::FACTURA_URL, [
                'headers' => [
                    'Cookie' => $cookieHeader,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => self::BASE_URL,
                ]
            ]);

            $html = (string) $resFac->getBody();

            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $rows = $xpath->query('//table[@id="tablaFacturado"]//tr[td]');
            $documents = [];

            foreach ($rows as $tr) {
                $tds = $xpath->query('td', $tr);
                if ($tds->length < 7) {
                    continue;
                }

                $sucursal = trim($tds->item(0)->textContent);
                $nroControl = trim($tds->item(1)->textContent);
                $documento = trim($tds->item(2)->textContent);
                $fecha = trim($tds->item(3)->textContent);
                $hora = trim($tds->item(4)->textContent);
                $totalBs = $this->parseAmount(trim($tds->item(5)->textContent));
                $totalUsd = $this->parseAmount(trim($tds->item(6)->textContent));

                $aTag = $xpath->query('td[8]//a', $tr)->item(0);
                $pdfUrl = $aTag ? $aTag->getAttribute('href') : null;

                $documents[] = [
                    'sucursal' => $sucursal,
                    'nro_control' => $nroControl,
                    'documento' => $documento,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'total_bs' => $totalBs,
                    'total_usd' => $totalUsd,
                    'pdf_url' => $pdfUrl,
                ];
            }

            return $documents;

        } catch (\Throwable $e) {
            Log::error('[DrocercaScraper] Error al extraer documentos: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return [];
        }
    }

    /**
     * Extrae el estado de cuenta y efectos por pagar en las 3 sedes (Mérida, Centro, Oriente)
     * retornando un array indexado por el número limpio de factura.
     */
    public function fetchEdoCuenta(string $username, string $password): array
    {
        $client = new Client([
            'verify' => false,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]
        ]);

        $edoCuentaMap = [];

        try {
            // Iniciar sesión en ProteoERP
            $loginRes = $client->post(self::LOGIN_URL, [
                'form_params' => [
                    'user' => $username,
                    'pws' => $password,
                ],
                'headers' => [
                    'Referer' => self::BASE_URL,
                ],
                'allow_redirects' => false
            ]);

            $setCookies = $loginRes->getHeader('Set-Cookie');
            $cookieHeader = '';
            foreach ($setCookies as $sc) {
                $parts = explode(';', $sc);
                $cookieHeader .= trim($parts[0]) . '; ';
            }

            if (empty($cookieHeader)) {
                return [];
            }

            // Consultar la sección de Edo. Cuenta / Pagos (donde están los Efectos por Pagar de las 3 sedes)
            $resPagos = $client->post(self::PAGOS_URL, [
                'headers' => [
                    'Cookie' => $cookieHeader,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => self::BASE_URL,
                ]
            ]);

            $html = (string) $resPagos->getBody();

            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            // Clases de las 3 sedes en el HTML de ProteoERP Drocerca
            $sedes = [
                'merida'  => 'fac--merida__content',
                'centro'  => 'fac--centro__content',
                'oriente' => 'fac--oriente__content',
            ];

            foreach ($sedes as $sedeKey => $sedeClass) {
                $tables = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $sedeClass ')]//table");
                foreach ($tables as $table) {
                    $rows = $xpath->query('.//tr', $table);
                    foreach ($rows as $tr) {
                        $tds = $xpath->query('td', $tr);
                        if ($tds->length >= 8) {
                            $rawNumero = trim($tds->item(0)->textContent);
                            $rawFIndexa = trim($tds->item(1)->textContent);
                            $rawVence = trim($tds->item(2)->textContent);
                            $rawDias = trim($tds->item(3)->textContent);
                            $rawMonto = trim($tds->item(4)->textContent);
                            $rawIva = trim($tds->item(5)->textContent);
                            $rawAplicado = trim($tds->item(6)->textContent);
                            $rawSaldo = trim($tds->item(7)->textContent);
                            $rawActualizado = $tds->length > 8 ? trim($tds->item(8)->textContent) : '';
                            $rawSaldoDolar = $tds->length > 9 ? trim($tds->item(9)->textContent) : '';

                            // Omitir cabeceras repetidas
                            if (str_contains($rawNumero, 'Número') || empty($rawNumero)) {
                                continue;
                            }

                            // Limpiar número de factura (ej: FC00909968 -> 909968 o 00909968)
                            $cleanDoc = preg_replace('/^[A-Za-z]+/', '', $rawNumero);
                            $cleanDocKey = ltrim($cleanDoc, '0');

                            $parsedIndexa = $this->parseDate($rawFIndexa);
                            $parsedVence = $this->parseDate($rawVence);

                            $itemData = [
                                'sede' => $sedeKey,
                                'numero_raw' => $rawNumero,
                                'numero_clean' => $cleanDoc,
                                'f_indexa' => $rawFIndexa,
                                'f_indexa_db' => $parsedIndexa,
                                'fecha_vencimiento' => $rawVence,
                                'fecha_vencimiento_db' => $parsedVence,
                                'dias' => (int) $rawDias,
                                'monto' => $this->parseAmount($rawMonto),
                                'saldo' => $this->parseAmount($rawSaldo),
                                'actualizado' => $this->parseAmount($rawActualizado),
                                'saldo_usd' => $this->parseAmount($rawSaldoDolar),
                            ];

                            $edoCuentaMap[$cleanDocKey] = $itemData;
                            $edoCuentaMap[$cleanDoc] = $itemData;
                            $edoCuentaMap[$rawNumero] = $itemData;
                        }
                    }
                }
            }

            return $edoCuentaMap;

        } catch (\Throwable $e) {
            Log::error('[DrocercaScraper] Error al extraer Edo. Cuenta de las 3 sedes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Descarga y parsea el PDF digital de Drocerca (NovusFactura) para extraer datos fiscales completos.
     */
    public function fetchAndParsePdf(string $pdfUrl, Client $client): ?array
    {
        try {
            $res = $client->get($pdfUrl, ['timeout' => 30]);
            $pdfContent = (string) $res->getBody();
            if (!str_starts_with($pdfContent, '%PDF')) {
                return null;
            }

            $parser = new Parser();
            $pdf = $parser->parseContent($pdfContent);
            $text = $pdf->getText();
            $normalized = preg_replace('/\t+/', ' ', $text);

            $numeroFactura = '';
            $numeroControl = '';
            $fechaEmision = null;
            $fechaVencimiento = null;
            $tasa = 1.0;
            $exento = 0.0;
            $baseImponible = 0.0;
            $iva = 0.0;
            $totalBs = 0.0;
            $totalUsd = 0.0;

            if (preg_match('/No\.\s*de\s*Factura:\s*([0-9A-Z]+)/iu', $normalized, $m)) {
                $numeroFactura = trim($m[1]);
            }
            if (preg_match('/No\.\s*de\s*Control\s*([0-9\-]+)/iu', $normalized, $m)) {
                $numeroControl = trim($m[1]);
            }
            if (preg_match('/Fecha\s*de\s*Emisi[oó]n:\s*([0-9]{2}[\-\/][0-9]{2}[\-\/][0-9]{4})/iu', $normalized, $m)) {
                $d = \DateTime::createFromFormat('d/m/Y', str_replace('-', '/', trim($m[1])));
                if ($d) $fechaEmision = $d->format('Y-m-d');
            }
            if (preg_match('/Fecha\s*de\s*Vencimiento:\s*([0-9]{4}[\-\/][0-9]{2}[\-\/][0-9]{2})/iu', $normalized, $m)) {
                $fechaVencimiento = trim($m[1]);
            } elseif (preg_match('/Fecha\s*de\s*Vencimiento:\s*([0-9]{2}[\-\/][0-9]{2}[\-\/][0-9]{4})/iu', $normalized, $m)) {
                $d = \DateTime::createFromFormat('d/m/Y', str_replace('-', '/', trim($m[1])));
                if ($d) $fechaVencimiento = $d->format('Y-m-d');
            }

            if (preg_match('/Tipo\s*de\s*Cambio\s*\(USD\s*\$\):\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $tasa = $this->parseAmount(trim($m[1]));
            }

            // Totales fiscales: Base Imponible Exenta, Base Imponible Alic. General, IVA, TOTAL
            if (preg_match('/Base\s*Imponible\s*Exenta\(E\):\s*Bs\.\s*([0-9\.,]+)\s*\$\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $exento = $this->parseAmount($m[1]);
            }
            if (preg_match('/Base\s*Imponible\s*Alic\.\s*General:\s*Bs\.\s*([0-9\.,]+)\s*\$\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $baseImponible = $this->parseAmount($m[1]);
            }
            if (preg_match('/IVA\s*Alicuota\s*General[^\:]*:\s*Bs\.\s*([0-9\.,]+)\s*\$\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $iva = $this->parseAmount($m[1]);
            }
            if (preg_match('/TOTAL\s*Bs\.\s*([0-9\.,]+)\s*\$\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $totalBs = $this->parseAmount($m[1]);
                $totalUsd = $this->parseAmount($m[2]);
            }

            if ($totalBs == 0 && ($exento > 0 || $baseImponible > 0)) {
                $totalBs = round($exento + $baseImponible + $iva, 2);
            }
            if ($totalUsd == 0 && $tasa > 0 && $totalBs > 0) {
                $totalUsd = round($totalBs / $tasa, 2);
            }

            // Guardar permanentemente el PDF en storage/app/public/invoices/
            $cleanDocName = preg_replace('/[^A-Za-z0-9_\-]/', '', $numeroFactura ?: 'drocerca');
            $pdfFileName = "invoice_drocerca_{$cleanDocName}_" . time() . ".pdf";
            $pdfStorageRelPath = "invoices/{$pdfFileName}";
            Storage::disk('public')->put($pdfStorageRelPath, $pdfContent);

            return [
                'numero_factura' => $numeroFactura,
                'control_number' => $numeroControl,
                'created_invoice_date' => $fechaEmision,
                'payment_due_date' => $fechaVencimiento,
                'exchange_rate' => $tasa,
                'exempt_amount' => $exento,
                'taxable_base' => $baseImponible,
                'tax_amount' => $iva,
                'total_amount' => $totalBs,
                'total_usd' => $totalUsd,
                'invoice_photo' => $pdfStorageRelPath,
            ];
        } catch (\Throwable $e) {
            Log::warning("[DrocercaScraper] Error leyendo PDF {$pdfUrl}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parsea un monto en formato español (12.329,03) o internacional (12329.03) a float.
     */
    private function parseAmount(string $amountStr): float
    {
        $cleaned = trim($amountStr);
        if (empty($cleaned)) {
            return 0.0;
        }

        // Si contiene coma como decimal (ej: 12.329,03)
        if (str_contains($cleaned, ',')) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Parsea una fecha en formato dd/mm/yy o dd/mm/yyyy o Y-m-d a formato Y-m-d.
     */
    private function parseDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        try {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $dateStr, $m)) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year = $m[3];
                if (strlen($year) === 2) {
                    $year = '20' . $year;
                }
                return "$year-$month-$day";
            }

            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
