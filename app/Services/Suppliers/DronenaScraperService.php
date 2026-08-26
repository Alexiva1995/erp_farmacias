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
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array
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

        if (empty($documents)) {
            return [
                'total_extracted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'supplier_id' => $supplierId,
                'details' => [],
            ];
        }

        // Si se especificó una factura única (ej. 43141520 o ID 6394)
        if ($onlyInvoice) {
            $targetNum = $onlyInvoice;
            if (is_numeric($onlyInvoice) && intval($onlyInvoice) < 100000) {
                // Podría ser un ID de BD
                $invById = Invoice::find((int)$onlyInvoice);
                if ($invById) {
                    $targetNum = $invById->invoice_number;
                }
            }
            $cleanTarget = ltrim($targetNum, 'A');
            $documents = array_values(array_filter($documents, function ($d) use ($cleanTarget, $targetNum) {
                $docClean = ltrim($d['numero_factura'], 'A');
                return $docClean === $cleanTarget || $d['numero_factura'] === $targetNum;
            }));
        }

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

            // Buscar si ya existe la factura registrada (exacto, con prefijo 'A', sin ceros a la izquierda, etc.)
            $cleanNumber = ltrim($invoiceNumber, 'A0');
            $possibleNumbers = array_unique([
                $invoiceNumber,
                ltrim($invoiceNumber, 'A'),
                $cleanNumber,
                'A' . $cleanNumber,
                'A' . ltrim($invoiceNumber, 'A'),
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                'A' . str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
            ]);

            $invoiceQuery = Invoice::where(function ($q) use ($possibleNumbers, $cleanNumber) {
                $q->whereIn('invoice_number', $possibleNumbers)
                  ->orWhere('invoice_number', 'LIKE', "%{$cleanNumber}");
            });
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

                // Si la factura no tiene PDF guardado, o le falta número de control o montos base, descargar y parsear el PDF
                if ((empty($invoice->invoice_photo) || empty($invoice->control_number) || $invoice->control_number === 'N/A' || floatval($invoice->total_amount) <= 0) && !empty($doc['pdf_url'])) {
                    $pdfData = $this->fetchAndParsePdf($doc['pdf_url'], $client);
                    if ($pdfData) {
                        if (!empty($pdfData['control_number'])) {
                            $updateData['control_number'] = $pdfData['control_number'];
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
                        if (!empty($pdfData['invoice_photo'])) {
                            $updateData['invoice_photo'] = $pdfData['invoice_photo'];
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
                            'invoice_photo' => $pdfData['invoice_photo'] ?? null,
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

        // Calcular discrepancias entre ERP y portal de Dronena
        $portalNumbers = [];
        foreach ($documents as $d) {
            $pClean = ltrim($d['numero_factura'], 'A0');
            $portalNumbers[$pClean] = $d;
        }

        $erpInvoicesQuery = Invoice::where('supplier_id', $supplierId);
        $erpInvoices = $erpInvoicesQuery->get();

        $paidInErpPendingInDronena = [];
        $pendingInErpPaidInDronena = [];

        foreach ($erpInvoices as $inv) {
            $invClean = ltrim($inv->invoice_number, 'A0');
            $isPaidInErp = ($inv->status_payment == 1);
            $isPendingInPortal = isset($portalNumbers[$invClean]);

            if ($isPaidInErp && $isPendingInPortal) {
                $pDoc = $portalNumbers[$invClean];
                $paidInErpPendingInDronena[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'control_number' => $inv->control_number,
                    'amount' => $inv->total_amount,
                    'currency' => $inv->currency,
                    'portal_amount' => $pDoc['saldo_db'] ?? $inv->total_amount,
                    'portal_type' => $pDoc['tipo_documento'] ?? 'FA',
                    'erp_status' => 'Pagada en ERP',
                    'portal_status' => 'Pendiente en Dronena',
                ];
            } elseif (!$isPaidInErp && !$isPendingInPortal) {
                $pendingInErpPaidInDronena[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'control_number' => $inv->control_number,
                    'amount' => $inv->total_amount,
                    'currency' => $inv->currency,
                    'erp_status' => 'Pendiente en ERP',
                    'portal_status' => 'Liquidada/No pendiente en Dronena',
                ];
            }
        }

        return [
            'total_extracted' => count($documents),
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'discrepancies' => [
                'paid_in_erp_pending_in_dronena' => $paidInErpPendingInDronena,
                'pending_in_erp_paid_in_dronena' => $pendingInErpPaidInDronena,
                'total_discrepancies' => count($paidInErpPendingInDronena) + count($pendingInErpPaidInDronena),
            ],
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
            
            // Buscar enlace de descarga de PDF digital en toda la fila (Priorizar Soluciones Laser)
            $pdfUrl = null;
            $laserUrl = null;
            $otherPdfUrl = null;

            foreach ($row->getElementsByTagName('a') as $aTag) {
                $href = $aTag->getAttribute('href');
                if (str_contains($href, 'solucioneslaser.com')) {
                    $laserUrl = $href;
                    break;
                }
                $onclick = $aTag->getAttribute('onclick');
                if (preg_match('/popUpFact\([\'"]([^\'"]+)[\'"]/i', $onclick, $mPop)) {
                    $popUrl = $mPop[1];
                    if (!str_starts_with($popUrl, 'http')) {
                        $popUrl = 'https://www.dronena.com/NuevaExperiencia/General/EstadoCuenta/' . basename($popUrl);
                    }
                    if (!$otherPdfUrl) {
                        $otherPdfUrl = $popUrl;
                    }
                }
            }

            $pdfUrl = $laserUrl ?: $otherPdfUrl;

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

            // Guardar permanentemente el PDF en storage/app/public/invoices/
            $pdfFileName = "invoice_{$serie}{$numeroFactura}_" . time() . ".pdf";
            $pdfStorageRelPath = "invoices/{$pdfFileName}";
            \Illuminate\Support\Facades\Storage::disk('public')->put($pdfStorageRelPath, $pdfContent);

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
                'invoice_photo' => $pdfStorageRelPath,
            ];
        } catch (\Throwable $e) {
            Log::warning("[DronenaScraper] Error leyendo PDF {$pdfUrl}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesa y reporta un pago directamente en el portal de Dronena (Cobranza/Pago/Pagos).
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank,
        ?string $paymentDate = null,
        ?string $receiptPathOrUrl = null,
        ?string $username = null,
        ?string $password = null
    ): array {
        $user = $username ?: env('DRONENA_USERNAME', 'D719');
        $pass = $password ?: env('DRONENA_PASSWORD', 'dronena2025');
        $date = $paymentDate ?: Carbon::now()->format('d/m/Y');
        if (str_contains($date, '-')) {
            $date = Carbon::parse($date)->format('d/m/Y');
        }

        // Tomar exactamente los últimos 10 dígitos de la referencia
        $digitsOnly = preg_replace('/\D/', '', $reference);
        $cleanRef = strlen($digitsOnly) >= 10 ? substr($digitsOnly, -10) : str_pad($digitsOnly, 10, '0', STR_PAD_LEFT);

        $jar = new CookieJar();
        $client = new Client([
            'cookies' => $jar,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
            'timeout' => 45,
        ]);

        try {
            // 1. Iniciar sesión en Dronena
            $initRes = $client->get(self::BASE_URL);
            preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', (string) $initRes->getBody(), $m);
            $token = $m[1] ?? '';

            $client->post(self::BASE_URL, [
                'form_params' => [
                    '__RequestVerificationToken' => $token,
                    'Login' => $user,
                    'Contraseña' => $pass,
                    'IdRol' => '3',
                ],
                'allow_redirects' => true,
            ]);

            // 2. Cargar página de Cobranza/Pago/Pagos para inicializar sesión de pagos
            $client->get('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/Pagos', ['allow_redirects' => true]);

            // 3. Consultar los movimientos / facturas pendientes en Dronena
            $movRes = $client->post('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/getMovimientos', [
                'form_params' => [
                    'CodCli' => $user,
                    'NroRecibo' => '',
                    'FechaDesde' => '',
                    'FechaHasta' => '',
                    'Estatus' => '0', // TODOS
                    'TipoMov' => '<TIPOMOV><TIPO>DC</TIPO><TIPO>FA</TIPO><TIPO>ND</TIPO><TIPO>NC</TIPO><TIPO>CH</TIPO><TIPO>GR</TIPO></TIPOMOV>',
                    'Clientes' => "<CLIENTES><CODIGO>{$user}</CODIGO></CLIENTES>",
                    'docTransito' => 'false',
                    'Moneda' => 'VES',
                    'FechaRef' => $date,
                    'TipoFecha' => '2', // PR. DE TASA
                    'RangoFecha' => 'false',
                ],
                'headers' => ['X-Requested-With' => 'XMLHttpRequest']
            ]);

            $movData = json_decode((string) $movRes->getBody(), true);
            $allRecibos = $movData['data'] ?? [];

            // Filtrar y armar lista de recibos a cancelar según las facturas enviadas
            $recibosXml = '';
            $montoTotalRecibos = 0;
            $cleanTargets = array_map(fn($n) => ltrim($n, 'A0'), $invoiceNumbers);

            foreach ($allRecibos as $r) {
                $reciboNum = (string) ($r['Recibo'] ?? '');
                $cleanRecibo = ltrim($reciboNum, 'A0');

                if (in_array($cleanRecibo, $cleanTargets) || in_array($reciboNum, $invoiceNumbers)) {
                    $tipoRbo = $r['TipoMov'] ?? 'FA$';
                    $codCli = $r['CodCliente'] ?? $user;
                    $montoRbo = (float) ($r['SaldoCancelar'] ?? $r['MontoACancelar'] ?? 0);
                    $tasaDia = $r['Tasa'] ?? '1';

                    $recibosXml .= "<RECIBO NRORBO=\"{$reciboNum}\" TIPORBO=\"{$tipoRbo}\" CODCLI=\"{$codCli}\" MONTORBO=\"{$montoRbo}\" TASADIA=\"{$tasaDia}\"/>";
                    $montoTotalRecibos += $montoRbo;
                }
            }

            if (empty($recibosXml) && !empty($invoiceNumbers)) {
                // Si no se encontró por coincidencia exacta, registrar con los números recibidos
                foreach ($invoiceNumbers as $invNum) {
                    $cleanInv = preg_replace('/\D/', '', $invNum);
                    $recibosXml .= "<RECIBO NRORBO=\"{$cleanInv}\" TIPORBO=\"FA$\" CODCLI=\"{$user}\" MONTORBO=\"{$paymentAmount}\" TASADIA=\"1\"/>";
                }
                $montoTotalRecibos = $paymentAmount;
            }

            // 4. Preparar comprobante en PDF (convertir automáticamente si es imagen)
            $pdfTempFile = $this->prepareReceiptPdf($receiptPathOrUrl, $cleanRef);
            $pdfFileName = basename($pdfTempFile);

            // 5. Subir comprobante temporal a Dronena (/Cobranza/Pago/GuardarArchivosTemp)
            $uploadRes = $client->post('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/GuardarArchivosTemp', [
                'multipart' => [
                    [
                        'name' => 'Files',
                        'contents' => fopen($pdfTempFile, 'r'),
                        'filename' => $pdfFileName,
                        'headers' => ['Content-Type' => 'application/pdf']
                    ]
                ],
                'headers' => ['X-Requested-With' => 'XMLHttpRequest']
            ]);

            $uploadJson = json_decode((string) $uploadRes->getBody(), true);

            // 6. Consultar código de cuenta destino en Dronena
            $bancosRes = $client->post('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/ConsultarBancos', [
                'json' => [
                    'TipoMoneda' => 'VES',
                    'TipoOperacion' => '1',
                ],
                'headers' => ['X-Requested-With' => 'XMLHttpRequest']
            ]);
            $bancosJson = json_decode((string) $bancosRes->getBody(), true);
            $codCuentaDestino = '';

            foreach ($bancosJson['data'] ?? [] as $b) {
                if (!empty($b['Nombre']) && !empty($destinationBank)) {
                    if (str_contains($destinationBank, $b['Nombre']) || str_contains($b['Nombre'], $destinationBank)) {
                        $codCuentaDestino = $b['Codigo'];
                        break;
                    }
                }
            }

            // Extraer los 20 dígitos limpios de la cuenta (Dronena requiere CTA="01020211670006291538")
            $cleanCta = preg_replace('/\D/', '', $codCuentaDestino ?: $destinationBank);
            if (strlen($cleanCta) > 20) {
                $cleanCta = substr($cleanCta, -20);
            }

            // 7. Verificar el pago antes de procesar (/Cobranza/Pago/VerificarPago)
            $client->post('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/VerificarPago', [
                'json' => [
                    'NumOperacion' => $cleanRef,
                    'TipoOperacion' => '1', // Transferencia
                    'CodCuenta' => $codCuentaDestino ?: $cleanCta,
                    'Monto' => $paymentAmount,
                ],
                'headers' => ['X-Requested-With' => 'XMLHttpRequest']
            ]);

            // 8. Construir XML completo tomando directamente el "Monto a Cancelar" oficial de Dronena
            $montoFinalPago = ($montoTotalRecibos > 0) ? $montoTotalRecibos : $paymentAmount;
            $montoBaseFormatted = number_format($montoFinalPago, 2, '.', '');
            $formattedDateDb = Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');

            $xmlPagosCancelar = '<RELACION>';
            $xmlPagosCancelar .= "<RECIBOS>{$recibosXml}</RECIBOS>";
            $xmlPagosCancelar .= '<PAGOS>';
            $xmlPagosCancelar .= "<PAGO TIPOPAG=\"1\" CTA=\"{$cleanCta}\" NROPAGO=\"{$cleanRef}\" MONTOPAG=\"{$montoBaseFormatted}\" FECHA=\"{$formattedDateDb}\" ADJUNTO=\"{$pdfFileName}\" MONTOBASE=\"{$montoBaseFormatted}\" TASAIGTF=\"0\" MONTOIGTF=\"0\">";
            $xmlPagosCancelar .= '<CHEQUES></CHEQUES>';
            $xmlPagosCancelar .= '</PAGO>';
            $xmlPagosCancelar .= '</PAGOS>';
            $xmlPagosCancelar .= '</RELACION>';



            // 9. Ejecutar el procesamiento final del pago en Dronena (/Cobranza/Pago/ProcesarPago)
            $procRes = $client->post('https://www.dronena.com/NuevaExperiencia/Cobranza/Pago/ProcesarPago', [
                'form_params' => [
                    'PagosCancelar' => $xmlPagosCancelar,
                    'IsoMoneda' => 'VES',
                ],
                'headers' => ['X-Requested-With' => 'XMLHttpRequest']
            ]);

            $procJson = json_decode((string) $procRes->getBody(), true);
            $pagoId = $procJson['data'] ?? 0;

            if ($pagoId > 0) {
                Log::info("[DronenaPayment] Pago registrado exitosamente en Dronena. ID: {$pagoId}, Ref: {$cleanRef}");
                return [
                    'success' => true,
                    'payment_id' => $pagoId,
                    'message' => "Pago registrado exitosamente en el portal de Dronena (ID: {$pagoId}).",
                    'clean_reference' => $cleanRef,
                ];
            } else {
                Log::warning("[DronenaPayment] Respuesta al procesar pago: " . json_encode($procJson));
                return [
                    'success' => false,
                    'payment_id' => null,
                    'message' => $procJson['message'] ?? 'No se pudo completar el procesamiento del pago en Dronena.',
                ];
            }

        } catch (\Throwable $e) {
            Log::error('[DronenaPayment] Error reportando pago en Dronena: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return [
                'success' => false,
                'message' => 'Error de conexión con Dronena: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convierte cualquier imagen o archivo a un PDF compatible < 1MB para Dronena.
     */
    private function prepareReceiptPdf(?string $receiptPathOrUrl, string $reference): string
    {
        $tempPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "soporte_{$reference}_" . time() . ".pdf";

        // Si ya es un archivo PDF local existente
        if (!empty($receiptPathOrUrl)) {
            $localPath = public_path(ltrim($receiptPathOrUrl, '/'));
            if (file_exists($localPath)) {
                $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    return $localPath;
                }

                // Es una imagen (png, jpg, jpeg, webp) -> convertir a PDF con Dompdf
                $imgData = base64_encode(file_get_contents($localPath));
                $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
                $src = "data:{$mime};base64,{$imgData}";

                $html = "<html><head><style>@page { margin: 0; size: letter portrait; } body { margin: 0; text-align: center; } img { max-width: 95%; max-height: 95%; margin: auto; display: block; }</style></head><body><img src=\"{$src}\"/></body></html>";

                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();

                file_put_contents($tempPdf, $dompdf->output());
                return $tempPdf;
            }
        }

        // Si no hay comprobante, generar PDF genérico con la referencia
        $html = "<html><head><style>body { font-family: sans-serif; text-align: center; padding: 50px; }</style></head><body><h2>COMPROBANTE DE PAGO</h2><p><b>Referencia:</b> {$reference}</p><p><b>Fecha:</b> " . date('d/m/Y H:i:s') . "</p></body></html>";
        $options = new \Dompdf\Options();
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        file_put_contents($tempPdf, $dompdf->output());
        return $tempPdf;
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


