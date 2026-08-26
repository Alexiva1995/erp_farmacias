<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\DronenaScraperServiceInterface;
use App\Models\Invoice;
use App\Models\Supplier;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class DronenaScraperService implements DronenaScraperServiceInterface
{
    private const BASE_URL = 'https://www.dronena.com/NuevaExperiencia/';
    private const ESTADO_CUENTA_URL = 'https://www.dronena.com/NuevaExperiencia/Cliente/Principal/EstadoCuenta';

    /**
     * Sincroniza las facturas de Dronena en la tabla invoices.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null): array
    {
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%NENA%')
                ->orWhere('name', 'LIKE', '%DRONENA%')
                ->first();
            $supplierId = $supplier?->id;
        }

        // Obtener conexión configurada en la BD para el bot de Dronena
        $conn = $supplier?->connections?->where('type', 'dronena_bot')->first() 
            ?? $supplier?->connections?->first();

        $user = $username;
        $pass = $password;

        if (!$user && $conn && $conn->type === 'dronena_bot' && !empty($conn->username)) {
            $user = $conn->username;
        }
        if (!$pass && $conn && $conn->type === 'dronena_bot' && !empty($conn->password)) {
            $pass = \App\Helpers\FtpCrypt::decrypt($conn->password);
        }

        // Fallbacks a variables de entorno o credenciales oficiales por defecto
        $user = $user ?: env('DRONENA_USERNAME', 'D719');
        $pass = $pass ?: env('DRONENA_PASSWORD', 'dronena2025');

        $documents = $this->fetchDocuments($user, $pass);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $processed = [];

        $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30]);
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        foreach ($documents as $doc) {
            $invoiceNumber = $doc['numero_factura'];
            $expDate = $doc['fecha_vencimiento_db'];
            $isFaDollar = $doc['is_indexed']; // TRUE si es FA$
            $currency = $doc['currency'];

            if (!$expDate || empty($invoiceNumber)) {
                $skippedCount++;
                continue;
            }

            // Regla: Se marca como indexada si es tipo FA$ O si la fecha de vencimiento ya pasó (vencida)
            $isOverdue = ($expDate < $today);
            $isIndexed = ($isFaDollar || $isOverdue);

            // Buscar si ya existe la factura registrada (exacto, con prefijo 'A' o sin prefijo 'A')
            $cleanNumber = ltrim($invoiceNumber, 'A');
            $possibleNumbers = array_unique([$invoiceNumber, $cleanNumber, 'A' . $cleanNumber]);

            $invoiceQuery = Invoice::whereIn('invoice_number', $possibleNumbers);
            if ($supplierId) {
                $invoiceQuery->where('supplier_id', $supplierId);
            }
            $invoice = $invoiceQuery->first();

            $claimAmount = $doc['monto_reclamo_db'] ?? 0;
            $ndRefAmount = $doc['monto_nd_referencial_db'] ?? 0;
            $netPayable = $doc['saldo_db'] ?? null;

            if ($invoice) {
                $updateData = [
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'claim_amount' => $claimAmount,
                    'nd_referential_amount' => $ndRefAmount,
                    'net_payable_amount' => $netPayable,
                ];

                // Si la factura no tiene número de control o montos base y hay enlace a PDF digital, extraer datos del PDF
                if ((empty($invoice->control_number) || $invoice->control_number === 'N/A' || floatval($invoice->total_amount) <= 0) && !empty($doc['pdf_url'])) {
                    $pdfData = $this->fetchAndParsePdf($doc['pdf_url'], $client);
                    if ($pdfData) {
                        if (!empty($pdfData['control_number'])) {
                            $updateData['control_number'] = $pdfData['control_number'];
                        }
                        if (!empty($pdfData['invoice_number'])) {
                            $updateData['invoice_number'] = $pdfData['invoice_number'];
                        }
                        if (!empty($pdfData['created_invoice_date']) && empty($invoice->created_invoice_date)) {
                            $updateData['created_invoice_date'] = $pdfData['created_invoice_date'];
                        }
                        if (floatval($pdfData['exchange_rate'] ?? 0) > 0 && floatval($invoice->exchange_rate ?? 0) <= 0) {
                            $updateData['exchange_rate'] = $pdfData['exchange_rate'];
                        }
                        if (floatval($pdfData['exempt_amount'] ?? 0) > 0 && floatval($invoice->exempt_amount ?? 0) <= 0) {
                            $updateData['exempt_amount'] = $pdfData['exempt_amount'];
                        }
                        if (floatval($pdfData['taxable_base'] ?? 0) > 0 && floatval($invoice->taxable_base ?? 0) <= 0) {
                            $updateData['taxable_base'] = $pdfData['taxable_base'];
                        }
                        if (floatval($pdfData['tax_amount'] ?? 0) > 0 && floatval($invoice->tax_amount ?? 0) <= 0) {
                            $updateData['tax_amount'] = $pdfData['tax_amount'];
                        }
                        if (floatval($pdfData['total_amount'] ?? 0) > 0 && floatval($invoice->total_amount ?? 0) <= 0) {
                            $updateData['total_amount'] = $pdfData['total_amount'];
                        }
                        if (floatval($pdfData['total_usd'] ?? 0) > 0 && floatval($invoice->total_usd ?? 0) <= 0) {
                            $updateData['total_usd'] = $pdfData['total_usd'];
                        }
                    }
                }

                $invoice->update($updateData);
                $updatedCount++;
                $processed[] = [
                    'invoice_number' => $invoice->invoice_number,
                    'action' => 'updated',
                    'control_number' => $invoice->control_number,
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'claim_amount' => $claimAmount,
                    'nd_referential_amount' => $ndRefAmount,
                    'net_payable_amount' => $netPayable,
                ];
            } else {
                // Factura no encontrada previamente en el ERP: si tiene PDF, crearla completa
                if (!empty($doc['pdf_url'])) {
                    $pdfData = $this->fetchAndParsePdf($doc['pdf_url'], $client);
                    if ($pdfData) {
                        $newInvoice = Invoice::create([
                            'supplier_id' => $supplierId,
                            'invoice_number' => $pdfData['invoice_number'] ?: ('A' . $cleanNumber),
                            'control_number' => $pdfData['control_number'] ?: null,
                            'created_invoice_date' => $pdfData['created_invoice_date'] ?: $doc['fecha_emision_db'],
                            'exp_date' => $expDate,
                            'payment_date' => $expDate,
                            'currency' => $currency,
                            'is_indexed' => $isIndexed,
                            'exempt_amount' => $pdfData['exempt_amount'] ?? 0,
                            'taxable_base' => $pdfData['taxable_base'] ?? 0,
                            'tax_amount' => $pdfData['tax_amount'] ?? 0,
                            'total_amount' => $pdfData['total_amount'] ?: $doc['monto_db'],
                            'total_usd' => $pdfData['total_usd'] ?: ($doc['tasa_db'] > 0 ? round($doc['monto_db'] / $doc['tasa_db'], 2) : 0),
                            'exchange_rate' => $pdfData['exchange_rate'] ?: $doc['tasa_db'],
                            'claim_amount' => $claimAmount,
                            'nd_referential_amount' => $ndRefAmount,
                            'net_payable_amount' => $netPayable,
                            'status' => 'pending',
                            'status_payment' => 0,
                            'uploaded_by' => 1,
                            'registered_by' => 1,
                        ]);
                        $updatedCount++;
                        $processed[] = [
                            'invoice_number' => $newInvoice->invoice_number,
                            'action' => 'created_from_pdf',
                            'control_number' => $newInvoice->control_number,
                            'exp_date' => $expDate,
                            'payment_date' => $expDate,
                            'is_indexed' => $isIndexed,
                        ];
                        continue;
                    }
                }

                $skippedCount++;
                $processed[] = [
                    'invoice_number' => $invoiceNumber,
                    'action' => 'not_found',
                    'exp_date' => $expDate,
                    'payment_date' => $expDate,
                    'is_indexed' => $isIndexed,
                ];
            }
        }

        return [
            'total_extracted' => count($documents),
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'details' => $processed,
        ];
    }

    /**
     * Extrae el listado de documentos directamente de la web de Dronena.
     */
    public function fetchDocuments(string $username, string $password): array
    {
        $jar = new CookieJar();
        $client = new Client([
            'cookies' => $jar,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
            'timeout' => 45,
        ]);

        // 1. Obtener token de verificación de la página principal
        $initialRes = $client->get(self::BASE_URL);
        preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', (string) $initialRes->getBody(), $matches);
        $token = $matches[1] ?? '';

        // 2. Realizar Login con rol de facturador (IdRol = 3)
        $client->post(self::BASE_URL, [
            'form_params' => [
                '__RequestVerificationToken' => $token,
                'Login' => $username,
                'Contraseña' => $password,
                'IdRol' => '3',
            ],
            'allow_redirects' => true,
        ]);

        // 3. Consultar contenedor de Estado de Cuenta
        $estadoRes = $client->get(self::ESTADO_CUENTA_URL, ['allow_redirects' => true]);
        preg_match('/src="(https:\/\/[^"]*EdoCuentaCli\.aspx[^"]*)"/i', (string) $estadoRes->getBody(), $iframeMatches);
        $iframeUrl = html_entity_decode($iframeMatches[1] ?? '');

        if (empty($iframeUrl)) {
            Log::warning('No se pudo localizar la URL del iframe de Estado de Cuenta en Dronena.');
            return [];
        }

        // 4. Descargar vista ASPX del estado de cuenta inicial
        $iframeRes = $client->get($iframeUrl, [
            'headers' => ['Referer' => self::ESTADO_CUENTA_URL],
        ]);
        $iframeHtml = (string) $iframeRes->getBody();

        // 5. Extraer campos de estado de ASP.NET para presionar el botón "Buscar" (Estatus: TODOS, Fecha Por: VENCIMIENTO)
        $domInit = new DOMDocument();
        @$domInit->loadHTML($iframeHtml);
        $xpathInit = new DOMXPath($domInit);

        $viewState = $xpathInit->query('//input[@name="__VIEWSTATE"]')->item(0)?->getAttribute('value') ?? '';
        $eventValidation = $xpathInit->query('//input[@name="__EVENTVALIDATION"]')->item(0)?->getAttribute('value') ?? '';
        $viewStateGen = $xpathInit->query('//input[@name="__VIEWSTATEGENERATOR"]')->item(0)?->getAttribute('value') ?? '';

        $postData = [
            '__EVENTTARGET' => '',
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => $viewState,
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfCodCli' => $username,
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfClientes' => "<CLIENTES><CODIGO>{$username}</CODIGO></CLIENTES>",
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfDesde' => '01/01/1980',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfHasta' => '01/01/1980',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfTipoMoneda' => 'VES',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfTipoMov' => '<TIPOMOV><TIPO>DC</TIPO><TIPO>FA</TIPO><TIPO>ND</TIPO><TIPO>NC</TIPO><TIPO>CH</TIPO><TIPO>GR</TIPO></TIPOMOV>',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfEstatus' => '0', // TODOS
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$HdfTipoFecha' => '0', // VENCIMIENTO
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$cmbEmpresas' => $username,
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$txtNumRecibo' => '',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$txtMov' => 'TODOS',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$cmbTipoVenc' => '0', // TODOS
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$cmbTipoFecha' => '0', // VENCIMIENTO
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$TxtFechaDesdePago' => '',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$TxtFechaHastaPago' => '',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$cmbTipoMoneda' => 'VES',
            'ctl00$ctl00$ContentPlaceHolder1$Contenedor$BtnBuscar' => 'Buscar',
        ];

        if ($eventValidation) {
            $postData['__EVENTVALIDATION'] = $eventValidation;
        }
        if ($viewStateGen) {
            $postData['__VIEWSTATEGENERATOR'] = $viewStateGen;
        }

        try {
            $searchRes = $client->post($iframeUrl, [
                'headers' => ['Referer' => $iframeUrl],
                'form_params' => $postData,
            ]);
            $finalHtml = (string) $searchRes->getBody();
        } catch (\Throwable $e) {
            Log::warning('Error en búsqueda completa de Dronena, usando vista inicial: ' . $e->getMessage());
            $finalHtml = $iframeHtml;
        }

        // 6. Parsear filas del estado de cuenta
        $dom = new DOMDocument();
        @$dom->loadHTML($finalHtml);
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//table[@id="TableMovimientos"]//tr');
        if ($rows->length === 0) {
            $rows = $xpath->query('//table[contains(@id, "ContentPlaceHolder1_Contenedor_GrvEdoCta")]//tr');
        }
        if ($rows->length === 0) {
            $rows = $xpath->query('//table//tr');
        }

        $documents = [];

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            if ($cols->length < 10) {
                continue;
            }

            $tipo = trim($cols->item(1)->textContent);

            // Filtrar solo facturas FA o FA$
            if (!in_array($tipo, ['FA', 'FA$'])) {
                continue;
            }

            $firstLink = $cols->item(2)->getElementsByTagName('a')->item(0);
            $recibo = $firstLink ? trim($firstLink->textContent) : trim(explode(' ', trim($cols->item(2)->textContent))[0]);
            
            // Buscar enlace de descarga de PDF digital (Soluciones Laser / Dronena)
            $pdfUrl = null;
            foreach ($cols->item(2)->getElementsByTagName('a') as $aTag) {
                $href = $aTag->getAttribute('href');
                if (str_contains($href, 'solucioneslaser.com') || str_contains($href, '.pdf')) {
                    $pdfUrl = $href;
                    break;
                }
            }

            $fechaMovimiento = trim($cols->item(3)->textContent);
            $fechaTope = trim($cols->item(5)->textContent);
            $fechaVencimiento = trim($cols->item(6)->textContent);
            $monto = trim($cols->item(8)->textContent);
            $retencion = trim($cols->item(9)->textContent);
            $ndReferencial = trim($cols->item(11)->textContent);
            $reclamo = trim($cols->item(12)->textContent);
            $saldo = trim($cols->item(13)->textContent);
            $tasa = trim($cols->item(14)->textContent);

            // Regla solicitada:
            // Si es FA$ => Factura Dolarizada => Marcar como INDEXADA (true)
            // Si es FA => Factura en Bs.S => NO marcar como indexada (false)
            $isIndexed = ($tipo === 'FA$');
            $currency = ($tipo === 'FA$') ? 'USD' : 'Bs';

            $parsedVencimiento = $this->parseDate($fechaVencimiento);
            $parsedEmision = $this->parseDate($fechaMovimiento);
            $parsedTope = $this->parseDate($fechaTope);

            $parsedMonto = $this->parseAmount($monto);
            $parsedSaldo = $this->parseAmount($saldo);
            $parsedNdRef = $this->parseAmount($ndReferencial);
            $parsedReclamo = $this->parseAmount($reclamo);
            $parsedTasa = $this->parseAmount($tasa);

            $documents[] = [
                'tipo' => $tipo,
                'numero_factura' => $recibo,
                'pdf_url' => $pdfUrl,
                'fecha_emision' => $fechaMovimiento,
                'fecha_emision_db' => $parsedEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_vencimiento_db' => $parsedVencimiento,
                'fecha_tope' => $fechaTope,
                'fecha_tope_db' => $parsedTope,
                'monto' => $monto,
                'monto_db' => $parsedMonto,
                'saldo' => $saldo,
                'saldo_db' => $parsedSaldo,
                'monto_nd_referencial' => $ndReferencial,
                'monto_nd_referencial_db' => $parsedNdRef,
                'monto_reclamo' => $reclamo,
                'monto_reclamo_db' => $parsedReclamo,
                'tasa' => $tasa,
                'tasa_db' => $parsedTasa,
                'currency' => $currency,
                'is_indexed' => $isIndexed,
            ];
        }

        return $documents;
    }

    /**
     * Descarga y parsea el PDF digital de Dronena para extraer datos fiscales completos.
     */
    public function fetchAndParsePdf(string $pdfUrl, Client $client): ?array
    {
        try {
            $res = $client->get($pdfUrl, ['timeout' => 30]);
            $pdfContent = (string) $res->getBody();
            if (!str_starts_with($pdfContent, '%PDF')) {
                return null;
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseContent($pdfContent);
            $text = $pdf->getText();
            $normalized = preg_replace('/\t+/', ' ', $text);

            $serie = 'A';
            $numeroFactura = '';
            $numeroControl = '';
            $fechaEmision = null;
            $tasa = 1.0;
            $exento = 0.0;
            $baseImponible = 0.0;
            $iva = 0.0;
            $totalBs = 0.0;
            $totalUsd = 0.0;

            if (preg_match('/Serie:\s*([A-Z0-9]+)/iu', $normalized, $m)) {
                $serie = trim($m[1]);
            }
            if (preg_match('/N[uú]mero\s*de\s*Documento:\s*(\d+)/iu', $normalized, $m)) {
                $numeroFactura = trim($m[1]);
            }
            if (preg_match('/N[uú]mero\s*de\s*Control:\s*([0-9\-]+)/iu', $normalized, $m)) {
                $numeroControl = trim($m[1]);
            }
            if (preg_match('/Fecha\s*de\s*Emisi[oó]n:\s*([0-9]{2}[\-\/][0-9]{2}[\-\/][0-9]{4})/iu', $normalized, $m)) {
                $d = \DateTime::createFromFormat('d-m-Y', str_replace('/', '-', trim($m[1])));
                if ($d) $fechaEmision = $d->format('Y-m-d');
            }
            if (preg_match('/Tipo\s*de\s*Cambio\s*\(USA\s*\$\)\s*Bs\.?\s*([0-9\.,]+)/iu', $normalized, $m)) {
                $tasa = (float) str_replace(',', '', trim($m[1]));
            }

            // Pie de totales fiscales
            if (preg_match_all('/Bs\.\s*([0-9]{1,3}(?:,[0-9]{3})*\.[0-9]{2}|[0-9]+\.[0-9]{2})/iu', $normalized, $bsMatches)) {
                $bsList = array_map(fn($v) => (float) str_replace(',', '', $v), $bsMatches[1]);
                for ($i = 0; $i <= count($bsList) - 9; $i++) {
                    $slice = array_slice($bsList, $i, 9);
                    $calcTot = round($slice[4] + $slice[6] + $slice[7], 2);
                    if (abs($calcTot - $slice[8]) < 0.05 && $slice[8] > 0) {
                        $exento = $slice[4];
                        $baseImponible = $slice[6];
                        $iva = $slice[7];
                        $totalBs = $slice[8];
                        break;
                    }
                }
            }

            if (preg_match_all('/\$\s*([0-9]{1,3}(?:,[0-9]{3})*\.[0-9]{2}|[0-9]+\.[0-9]{2})/iu', $normalized, $usdMatches)) {
                $usdList = array_map(fn($v) => (float) str_replace(',', '', $v), $usdMatches[1]);
                for ($i = 0; $i <= count($usdList) - 9; $i++) {
                    $slice = array_slice($usdList, $i, 9);
                    if ($slice[8] > 0) {
                        $totalUsd = $slice[8];
                        break;
                    }
                }
            }

            if ($totalBs == 0 && ($exento > 0 || $baseImponible > 0)) {
                $totalBs = round($exento + $baseImponible + $iva, 2);
            }
            if ($totalUsd == 0 && $tasa > 0 && $totalBs > 0) {
                $totalUsd = round($totalBs / $tasa, 2);
            }

            return [
                'serie' => $serie,
                'numero_factura' => $numeroFactura,
                'invoice_number' => "{$serie}{$numeroFactura}",
                'control_number' => $numeroControl,
                'created_invoice_date' => $fechaEmision,
                'exchange_rate' => $tasa,
                'exempt_amount' => $exento,
                'taxable_base' => $baseImponible,
                'tax_amount' => $iva,
                'total_amount' => $totalBs,
                'total_usd' => $totalUsd,
            ];
        } catch (\Throwable $e) {
            Log::warning("[DronenaScraper] Error leyendo PDF {$pdfUrl}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parsea un monto en formato español (1.234,56) a float.
     */
    private function parseAmount(string $amountStr): float
    {
        $cleaned = str_replace('.', '', trim($amountStr));
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }

    /**
     * Parsea una fecha en formato dd/mm/yy o dd/mm/yyyy a formato Y-m-d.
     */
    private function parseDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $dateStr, $m)) {
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
