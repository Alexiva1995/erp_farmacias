<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MafartaScraperService implements MafartaScraperServiceInterface
{
    private const BASE_URL = 'https://sic.drogueriascobeca.com';
    private const LOGIN_URL = 'https://sic.drogueriascobeca.com/api/auth/login';
    private const ESTADO_CUENTA_URL = 'https://sic.drogueriascobeca.com/api/estadocuenta/consulta';
    private const FACTURA_URL = 'https://sic.drogueriascobeca.com/api/factura';

    /**
     * Sincroniza las facturas de Mafarta/Cobeca en la base de datos del ERP:
     * - Detecta facturas indexadas (facturaDolari === 1 o vencidas).
     * - Actualiza fechas de vencimiento reales, montos y número de control oficial.
     */
    public function syncInvoices(?string $username = null, ?string $password = null, ?int $supplierId = null, ?string $onlyInvoice = null): array
    {
        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::with('connections')->find($supplierId);
        } else {
            $supplier = Supplier::with('connections')
                ->where('name', 'LIKE', '%MAFARTA%')
                ->orWhere('name', 'LIKE', '%COBECA%')
                ->orWhere('id', 23)
                ->first();
            $supplierId = $supplier?->id ?? 23;
        }

        // Obtener conexión configurada en la BD para el bot de Mafarta
        $conn = $supplier?->connections?->where('type', 'mafarta_bot')->first() 
            ?? $supplier?->connections?->first();

        $user = $username;
        $pass = $password;

        if (!$user && $conn && !empty($conn->username)) {
            $user = $conn->username;
        }
        if (!$pass && $conn && !empty($conn->password)) {
            $pass = \App\Helpers\FtpCrypt::decrypt($conn->password);
        }

        $user = $user ?: env('MAFARTA_USERNAME', 'F31373');
        $pass = $pass ?: env('MAFARTA_PASSWORD', 'Mafarta2026*');

        $result = $this->fetchDocumentsWithToken($user, $pass);
        $documents = $result['documents'] ?? [];
        $token = $result['token'] ?? null;

        if (empty($documents)) {
            return [
                'total_extracted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'supplier_id' => $supplierId,
                'details' => [],
            ];
        }

        if ($onlyInvoice) {
            $targetNum = ltrim((string) $onlyInvoice, '0');
            $documents = array_values(array_filter($documents, function ($d) use ($targetNum, $onlyInvoice) {
                $docClean = ltrim((string) $d['numDoc'], '0');
                return $docClean === $targetNum || $d['numDoc'] === $onlyInvoice;
            }));
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $processed = [];
        $today = Carbon::now()->format('Y-m-d');
        $userId = \App\Models\User::first()?->id ?? 1;

        foreach ($documents as $doc) {
            $rawDocNum = (string) ($doc['numDoc'] ?? '');
            $cleanNumber = ltrim($rawDocNum, '0');
            $tipoDoc = strtoupper((string) ($doc['tipoDoc'] ?? 'FA'));
            $isNC = ($tipoDoc === 'NC');

            $emisionDate = !empty($doc['fechEmision']) ? Carbon::parse($doc['fechEmision'])->format('Y-m-d') : null;
            // Para las NC, el vencimiento es SIEMPRE la fecha de emisión
            $expDate = $isNC ? ($emisionDate ?: $today) : (!empty($doc['fechVenc']) ? Carbon::parse($doc['fechVenc'])->format('Y-m-d') : null);

            // En Cobeca/Mafarta una factura es indexada ÚNICAMENTE si el portal lo define explícitamente:
            // 1. facturaDolari es true/1, O
            // 2. Tiene tasa de conversión activa (montoTasaConv > 0), O
            // 3. Tiene monto diferencial cambiario (montoDifer > 0)
            // (Las Notas de Crédito y facturas en Bs fijas con facturaDolari=false NO son indexadas)
            $hasDolariFlag = isset($doc['facturaDolari']) && filter_var($doc['facturaDolari'], FILTER_VALIDATE_BOOLEAN);
            $hasConvRate = (float) ($doc['montoTasaConv'] ?? 0) > 0;
            $hasDifer = (float) ($doc['montoDifer'] ?? 0) > 0;

            $isIndexed = !$isNC && ($hasDolariFlag || $hasConvRate || $hasDifer);

            if (empty($cleanNumber)) {
                $skippedCount++;
                continue;
            }

            // Identificador en ERP (ej: NC-1635384 para Notas de Crédito o correlativo numérico)
            $docPrefix = $isNC ? 'NC-' : '';
            $erpDocNumber = $docPrefix . $cleanNumber;

            // Buscar la factura o NC en el ERP por múltiples variantes
            $possibleNumbers = array_unique([
                $erpDocNumber,
                $docPrefix . $rawDocNum,
                $cleanNumber,
                $rawDocNum,
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
                'NC-' . str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                'NC-' . str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
            ]);

            $invoice = Invoice::where('supplier_id', $supplierId)
                ->where(function ($query) use ($possibleNumbers) {
                    $query->whereIn('invoice_number', $possibleNumbers);
                })
                ->first();

            // Consultar detalle completo en el API si falta nroControl o detalles
            $controlNumber = $doc['nroControl'] ?? null;
            $ndRefAmount = 0; // En Cobeca/Mafarta el diferencial es producto de indexacion natural, no una ND a descontar
            
            $rawTotal = (float) ($doc['montoTotal'] ?? $doc['montoDoc'] ?? 0);
            $rawTotalUsd = (float) ($doc['montoTotal2'] ?? 0);
            $exchangeRate = (float) ($doc['montoTasaConv'] ?? $doc['montoTasaFact'] ?? 0);

            // Montos con signo según el tipo de documento (Negativos para NC para restar de la deuda)
            $totalAmount = $isNC ? -abs($rawTotal) : abs($rawTotal);
            $totalUsd = $isNC ? -abs($rawTotalUsd ?: ($exchangeRate > 0 ? round(abs($rawTotal) / $exchangeRate, 2) : 0)) : abs($rawTotalUsd);
            $exemptAmount = $isNC ? -abs((float) ($doc['montoExento'] ?? 0)) : (float) ($doc['montoExento'] ?? 0);
            $taxAmount = $isNC ? -abs((float) ($doc['montoIva'] ?? 0)) : (float) ($doc['montoIva'] ?? 0);
            $taxableBase = $isNC ? -abs((float) ($doc['montoBase'] ?? (abs($totalAmount) - abs($exemptAmount)))) : (float) ($doc['montoBase'] ?? ($totalAmount - $exemptAmount));

            if ($token && empty($controlNumber)) {
                $detailData = $this->getInvoiceDetail($rawDocNum, $token);
                if ($detailData) {
                    $controlNumber = $detailData['nroControl'] ?? $controlNumber;
                    if (!$isNC && empty($expDate) && !empty($detailData['fechaVencimiento'])) {
                        $expDate = Carbon::parse($detailData['fechaVencimiento'])->format('Y-m-d');
                    }
                    if (empty($emisionDate) && !empty($detailData['fechaFactura'])) {
                        $emisionDate = Carbon::parse($detailData['fechaFactura'])->format('Y-m-d');
                    }
                }
            }

            // Si no tiene número de control, buscar en la BD si otro registro del ERP lo tiene
            if (empty($controlNumber) || $controlNumber === 'N/A') {
                $matchedControl = Invoice::where('supplier_id', $supplierId)
                    ->where(function ($q) use ($cleanNumber) {
                        $q->where('invoice_number', 'LIKE', "%{$cleanNumber}");
                    })
                    ->whereNotNull('control_number')
                    ->where('control_number', '!=', '')
                    ->where('control_number', '!=', 'N/A')
                    ->value('control_number');
                if ($matchedControl) {
                    $controlNumber = $matchedControl;
                }
            }

            if ($invoice) {
                $updateData = [];

                // Actualizar número de control si no lo tenía
                if (!empty($controlNumber) && (empty($invoice->control_number) || $invoice->control_number === '0' || $invoice->control_number === '00000000')) {
                    $updateData['control_number'] = $controlNumber;
                }

                // Actualizar fecha de vencimiento y de pago con la fecha oficial del portal (si no está pagada)
                if ($expDate) {
                    $updateData['exp_date'] = $expDate;
                    if ((int) ($invoice->status_payment ?? 0) !== 1) {
                        $updateData['payment_date'] = $expDate;
                    }
                }

                if ($emisionDate) {
                    $updateData['created_invoice_date'] = $emisionDate;
                }

                // Actualizar montos en NC para garantizar signo negativo
                if ($isNC) {
                    $updateData['total_amount'] = $totalAmount;
                    $updateData['total_usd'] = $totalUsd;
                    $updateData['taxable_base'] = $taxableBase;
                    $updateData['tax_amount'] = $taxAmount;
                    $updateData['exempt_amount'] = $exemptAmount;
                }

                // Actualizar estado de indexación
                $updateData['is_indexed'] = $isIndexed;

                // Limpiar ND referencial en Mafarta (no aplica)
                $updateData['nd_referential_amount'] = 0;

                if (!$isNC && $totalUsd > 0 && ((float) ($invoice->total_usd ?? 0) <= 0)) {
                    $updateData['total_usd'] = $totalUsd;
                }

                if ($exchangeRate > 0 && ((float) ($invoice->exchange_rate ?? 0) <= 0)) {
                    $updateData['exchange_rate'] = $exchangeRate;
                }

                // Generar y almacenar el PDF de la factura con la plantilla oficial
                if ($token) {
                    $detailData = $detailData ?? $this->getInvoiceDetail($rawDocNum, $token);
                    if ($detailData) {
                        $pdfPath = $this->generateAndStoreInvoicePdf($invoice, $detailData);
                        if ($pdfPath) {
                            $updateData['invoice_photo'] = $pdfPath;
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
                    'is_indexed' => $isIndexed,
                    'total_usd' => (float) $invoice->total_usd,
                    'nd_referential_amount' => 0,
                    'has_pdf' => !empty($invoice->invoice_photo) || !empty($updateData['invoice_photo']),
                ];
            } else {
                // Si la factura o NC no existe en el ERP, crearla automáticamente
                $calcUsd = $totalUsd;
                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'invoice_number' => $erpDocNumber,
                    'control_number' => $controlNumber,
                    'created_invoice_date' => $emisionDate ?: $today,
                    'exp_date' => $expDate ?: $today,
                    'payment_date' => $expDate ?: $today,
                    'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.00,
                    'exempt_amount' => $exemptAmount,
                    'taxable_base' => $taxableBase,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'total_usd' => $calcUsd,
                    'nd_referential_amount' => 0,
                    'is_indexed' => $isIndexed,
                    'status' => 'pending',
                    'status_payment' => 0,
                    'uploaded_by' => $userId,
                    'registered_by' => $userId,
                ]);

                $createdCount++;

                $hasPdf = false;
                if ($token && !empty($detailData)) {
                    $pdfPath = $this->generateAndStoreInvoicePdf($newInvoice, $detailData);
                    if ($pdfPath) {
                        $newInvoice->update(['invoice_photo' => $pdfPath]);
                        $hasPdf = true;
                    }
                }

                $processed[] = [
                    'invoice_number' => $newInvoice->invoice_number,
                    'action' => 'created',
                    'control_number' => $newInvoice->control_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_usd' => $calcUsd,
                    'nd_referential_amount' => 0,
                    'has_pdf' => $hasPdf,
                ];
            }
        }

        // Calcular discrepancias entre ERP y portal de Cobeca / Mafarta
        $portalDocNumbers = [];
        foreach ($documents as $d) {
            $pClean = ltrim((string) ($d['numDoc'] ?? ''), '0');
            $portalDocNumbers[$pClean] = $d;
            $portalDocNumbers[(string) ($d['numDoc'] ?? '')] = $d;
        }

        $erpInvoices = Invoice::where('supplier_id', $supplierId)->get([
            'id', 'invoice_number', 'control_number', 'total_amount', 'currency', 'status_payment'
        ]);
        $paidInErpPendingInMafarta = [];
        $pendingInErpPaidInMafarta = [];

        foreach ($erpInvoices as $inv) {
            $digitsOnly = preg_replace('/\D/', '', $inv->invoice_number);
            $invClean = ltrim($digitsOnly ?: $inv->invoice_number, '0');
            $isPaidInErp = ($inv->status_payment == 1);
            $isPendingInPortal = isset($portalDocNumbers[$invClean]) || isset($portalDocNumbers[$inv->invoice_number]);

            $controlNumber = $inv->control_number;
            if (empty($controlNumber) || $controlNumber === 'N/A') {
                $matchedControl = Invoice::where('supplier_id', $inv->supplier_id)
                    ->where(function ($q) use ($invClean) {
                        $q->where('invoice_number', 'LIKE', "%{$invClean}");
                    })
                    ->whereNotNull('control_number')
                    ->where('control_number', '!=', '')
                    ->where('control_number', '!=', 'N/A')
                    ->value('control_number');
                if ($matchedControl) {
                    $controlNumber = $matchedControl;
                    $inv->update(['control_number' => $matchedControl]);
                }
            }

            if ($isPaidInErp && $isPendingInPortal) {
                $pDoc = $portalDocNumbers[$invClean] ?? $portalDocNumbers[$inv->invoice_number];
                $paidInErpPendingInMafarta[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'control_number' => $controlNumber,
                    'amount' => $inv->total_amount,
                    'currency' => $inv->currency,
                    'portal_amount' => $pDoc['montoTotal'] ?? $pDoc['montoDoc'] ?? $inv->total_amount,
                    'erp_status' => 'Pagada en ERP',
                    'portal_status' => 'Pendiente en Cobeca',
                ];
            } elseif (!$isPaidInErp && !$isPendingInPortal) {
                $pendingInErpPaidInMafarta[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'control_number' => $controlNumber,
                    'amount' => $inv->total_amount,
                    'currency' => $inv->currency,
                    'erp_status' => 'Pendiente en ERP',
                    'portal_status' => 'Liquidada en Cobeca',
                ];
            }
        }

        return [
            'total_extracted' => count($documents),
            'updated' => $updatedCount,
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'supplier_id' => $supplierId,
            'discrepancies' => [
                'paid_in_erp_pending_in_mafarta' => $paidInErpPendingInMafarta,
                'pending_in_erp_paid_in_mafarta' => $pendingInErpPaidInMafarta,
                'total_discrepancies' => count($paidInErpPendingInMafarta) + count($pendingInErpPaidInMafarta),
            ],
            'details' => $processed,
        ];
    }

    /**
     * Extrae los documentos desde el portal SIC de Cobeca autenticándose con JWT.
     */
    public function fetchDocuments(string $username, string $password): array
    {
        $res = $this->fetchDocumentsWithToken($username, $password);
        return $res['documents'] ?? [];
    }

    /**
     * Realiza login y devuelve documentos junto con el token.
     */
    public function fetchDocumentsWithToken(string $username, string $password): array
    {
        try {
            $user = $username ?: 'F31373';
            $pass = $password ?: 'Mafarta2026*';

            $loginRes = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withoutVerifying()->timeout(30)->post(self::LOGIN_URL, [
                'User' => $user,
                'Password' => $pass,
            ]);

            // Si falló el login con las credenciales dadas, intentar con las credenciales maestras de respaldo
            if ($loginRes->failed() || empty($loginRes->json('token'))) {
                $loginRes = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->withoutVerifying()->timeout(30)->post(self::LOGIN_URL, [
                    'User' => 'F31373',
                    'Password' => 'Mafarta2026*',
                ]);
            }

            if ($loginRes->failed()) {
                Log::error("[MAFARTA SCRAPER] Error en login: " . $loginRes->body());
                return ['documents' => [], 'token' => null];
            }

            $loginData = $loginRes->json();
            $token = $loginData['token'] ?? null;
            $clientCode = (int) ($loginData['client'] ?? 31373);
            $drugstore = (int) ($loginData['drogueria'] ?? 3);

            if (!$token) {
                Log::error("[MAFARTA SCRAPER] No se recibió token en respuesta de login.");
                return ['documents' => [], 'token' => null];
            }

            // Consultar Estado de Cuenta
            $docsRes = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withoutVerifying()->timeout(45)->post(self::ESTADO_CUENTA_URL, [
                'compania' => $drugstore,
                'drogueria' => $drugstore,
                'cliente' => $clientCode,
                'tipo' => 1,
            ]);

            if ($docsRes->failed()) {
                Log::error("[MAFARTA SCRAPER] Error al consultar estado de cuenta: " . $docsRes->body());
                return ['documents' => [], 'token' => $token];
            }

            $data = $docsRes->json();
            $documents = $data['estadoCuenta'] ?? $data['documentos'] ?? [];

            return [
                'documents' => $documents,
                'token' => $token,
            ];
        } catch (\Throwable $e) {
            Log::error("[MAFARTA SCRAPER] Excepción en fetchDocuments: " . $e->getMessage());
            return ['documents' => [], 'token' => null];
        }
    }

    /**
     * Consulta el detalle y número de control de una factura en el endpoint oficial.
     */
    public function getInvoiceDetail(string $invoiceNumber, string $token): ?array
    {
        try {
            $numDoc = str_pad(ltrim($invoiceNumber, '0'), 10, '0', STR_PAD_LEFT);
            $res = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->withoutVerifying()->timeout(20)->get(self::FACTURA_URL . "/{$numDoc}");

            if ($res->successful()) {
                return $res->json();
            }

            // Intentar sin padding si falló
            $cleanNum = ltrim($invoiceNumber, '0');
            $resClean = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->withoutVerifying()->timeout(20)->get(self::FACTURA_URL . "/{$cleanNum}");

            if ($resClean->successful()) {
                return $resClean->json();
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("[MAFARTA SCRAPER] Excepción al obtener detalle de factura #{$invoiceNumber}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera y almacena el archivo PDF digital de la factura para visualizarla en el ERP.
     */
    public function generateAndStoreInvoicePdf(Invoice $invoice, array $detailData): ?string
    {
        try {
            $invoice->loadMissing('details');

            if (!empty($invoice->invoice_photo) && \Illuminate\Support\Facades\Storage::disk('public')->exists($invoice->invoice_photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->invoice_photo);
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.mafarta_invoice', [
                'invoice' => $invoice,
                'detail' => $detailData,
            ])->setPaper('letter', 'portrait');

            $pdfFileName = "invoice_mafarta_{$invoice->invoice_number}_" . time() . ".pdf";
            $pdfStorageRelPath = "invoices/{$pdfFileName}";

            \Illuminate\Support\Facades\Storage::disk('public')->put($pdfStorageRelPath, $pdf->output());

            return $pdfStorageRelPath;
        } catch (\Throwable $e) {
            Log::warning("[MAFARTA SCRAPER] Error generando PDF para factura #{$invoice->invoice_number}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Reporta y procesa un pago directamente en el portal SIC de Cobeca / Mafarta.
     */
    public function submitPayment(
        array $invoiceNumbers,
        float $paymentAmount,
        string $reference,
        string $destinationBank,
        string $paymentDate,
        ?string $receiptPath = null,
        string $idType = 'V',
        string $idNumber = '24150980'
    ): array {
        try {
            Log::info("[COBECA PAYMENT] Iniciando reporte de pago para facturas: " . implode(', ', $invoiceNumbers));

            // 1. Obtener credenciales
            $supplier = Supplier::where('name', 'LIKE', '%MAFARTA%')
                ->orWhere('name', 'LIKE', '%COBECA%')
                ->first();

            $conn = $supplier?->connections?->where('type', 'mafarta_bot')->first() 
                ?? $supplier?->connections?->first();

            $username = $conn?->username ?: 'F31373';
            $password = null;
            if ($conn && !empty($conn->password)) {
                try {
                    $password = \App\Helpers\FtpCrypt::decrypt($conn->password);
                } catch (\Throwable $e) {
                    $password = $conn->password;
                }
            }
            $password = $password ?: env('MAFARTA_PASSWORD', 'Mafarta2026*');

            // 2. Iniciar sesión en el portal SIC
            $loginRes = Http::withoutVerifying()->post('https://sic.drogueriascobeca.com/api/auth/login', [
                'User' => $username,
                'Password' => $password,
            ]);

            if ($loginRes->status() !== 200 || empty($loginRes->json('token'))) {
                // Reintento con clave de respaldo oficial si falla
                $loginRes = Http::withoutVerifying()->post('https://sic.drogueriascobeca.com/api/auth/login', [
                    'User' => 'F31373',
                    'Password' => 'Mafarta2026*',
                ]);
            }

            if ($loginRes->status() !== 200 || empty($loginRes->json('token'))) {
                Log::error('[COBECA PAYMENT] Error de autenticación al reportar pago: ' . $loginRes->body());
                return [
                    'success' => false,
                    'message' => 'Error de autenticación con el portal de Cobeca.',
                ];
            }

            $token = $loginRes->json('token');
            $clientCode = (int) ($loginRes->json('client') ?? 31373);
            $drugstore = (int) ($loginRes->json('drogueria') ?? 3);

            // 3. Consultar estado de cuenta en vivo para obtener los documentos exactos y calcular el monto oficial de Cobeca
            $edoCuentaRes = Http::withHeaders(['Authorization' => "Bearer {$token}"])
                ->withoutVerifying()
                ->post('https://sic.drogueriascobeca.com/api/estadocuenta/consulta', [
                    'compania' => $drugstore,
                    'drogueria' => $drugstore,
                    'cliente' => $clientCode,
                    'tipo' => 1,
                ]);

            $rawDocs = $edoCuentaRes->json('estadoCuenta') ?? [];

            // Mapear números limpios buscados
            $cleanTargets = array_map(function ($num) {
                return ltrim(str_replace(['NC-', 'FA-', 'FB-', 'A', 'F'], '', trim((string) $num)), '0');
            }, $invoiceNumbers);

            $matchedDocs = [];
            $cobecaCalculatedTotal = 0.0;

            foreach ($rawDocs as $doc) {
                $cleanDocNum = ltrim(str_replace(['NC-', 'FA-', 'FB-', 'A', 'F'], '', trim((string) ($doc['numDoc'] ?? ''))), '0');
                if (in_array($cleanDocNum, $cleanTargets, true)) {
                    $matchedDocs[] = $doc;
                    $isNC = strtoupper($doc['tipoDoc'] ?? '') === 'NC';
                    $rowTotal = (float) ($doc['montoTotal'] ?? $doc['montoDoc'] ?? 0);
                    $cobecaCalculatedTotal += $isNC ? -abs($rowTotal) : abs($rowTotal);
                }
            }

            if (empty($matchedDocs)) {
                Log::warning('[COBECA PAYMENT] No se encontraron los documentos seleccionados en el estado de cuenta de Cobeca.');
                return [
                    'success' => false,
                    'message' => 'Los documentos seleccionados no fueron encontrados pendientes en el estado de cuenta de Cobeca.',
                ];
            }

            // Regla: Enviar el monto exacto calculado por Cobeca para evitar discrepancias por decimales/diferencial
            $finalPaymentAmount = ($cobecaCalculatedTotal > 0) ? round($cobecaCalculatedTotal, 2) : round($paymentAmount, 2);

            // 4. Formatear la referencia bancaria a los últimos 9 dígitos
            $cleanRef = preg_replace('/\D/', '', $reference);
            $ref9 = strlen($cleanRef) >= 9 ? substr($cleanRef, -9) : str_pad($cleanRef, 9, '0', STR_PAD_LEFT);

            // 5. Preparar la cuenta bancaria de destino (cuenta de Cobeca/Mafarta)
            $cleanDestinationBank = preg_replace('/\D/', '', $destinationBank);
            if (empty($cleanDestinationBank)) {
                $cleanDestinationBank = '01020219190006814326'; // Banco de Venezuela por defecto
            }

            $formattedPaymentDate = Carbon::parse($paymentDate)->format('Y-m-d');

            // 6. Preparar documentos para el payload de Cobeca
            $documentosPayload = [];
            foreach ($matchedDocs as $mDoc) {
                $documentosPayload[] = [
                    'numDoc' => (string) ($mDoc['numDoc'] ?? ''),
                    'Hoja' => (string) ($mDoc['hoja'] ?? 'A'),
                    'MontoRetencion' => (float) ($mDoc['montoRetencion'] ?? 0),
                    'tpDoc' => (string) ($mDoc['tipoDoc'] ?? 'FA'),
                    'montoPP' => (float) ($mDoc['montoTotal'] ?? $mDoc['montoDoc'] ?? 0),
                    'porcentajePP' => 0,
                    'ivaPP' => 0,
                ];
            }

            // 7. Preparar comprobante (archivo físico o generado)
            $fileContent = null;
            $fileName = 'comprobante_pago_' . time() . '.png';
            $fileMime = 'image/png';

            if (!empty($receiptPath)) {
                $candidatePaths = [];
                if (file_exists($receiptPath)) {
                    $candidatePaths[] = $receiptPath;
                }
                $cleanRelative = preg_replace('#^https?://[^/]+/#i', '', $receiptPath);
                $cleanRelative = ltrim($cleanRelative, '/');
                if (str_starts_with($cleanRelative, 'storage/')) {
                    $cleanRelative = substr($cleanRelative, 8);
                }
                $candidatePaths[] = storage_path('app/public/' . $cleanRelative);
                $candidatePaths[] = storage_path('app/' . $cleanRelative);
                $candidatePaths[] = public_path('storage/' . $cleanRelative);
                $candidatePaths[] = public_path($cleanRelative);

                foreach ($candidatePaths as $cand) {
                    if (!empty($cand) && file_exists($cand) && is_file($cand)) {
                        $fileContent = file_get_contents($cand);
                        $fileName = basename($cand);
                        $ext = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
                        $fileMime = ($ext === 'pdf') ? 'application/pdf' : (($ext === 'png') ? 'image/png' : 'image/jpeg');
                        break;
                    }
                }
            }

            if (!$fileContent) {
                // Generar un comprobante temporal en caso de no adjuntar archivo
                $dummyImage = imagecreatetruecolor(400, 200);
                $bgColor = imagecolorallocate($dummyImage, 240, 240, 240);
                $textColor = imagecolorallocate($dummyImage, 0, 47, 134);
                imagefill($dummyImage, 0, 0, $bgColor);
                imagestring($dummyImage, 5, 20, 30, "COMPROBANTE DE PAGO", $textColor);
                imagestring($dummyImage, 4, 20, 70, "Ref: {$ref9}", $textColor);
                imagestring($dummyImage, 4, 20, 100, "Monto: Bs. " . number_format($finalPaymentAmount, 2, ',', '.'), $textColor);
                imagestring($dummyImage, 4, 20, 130, "Fecha: {$formattedPaymentDate}", $textColor);
                ob_start();
                imagepng($dummyImage);
                $fileContent = ob_get_clean();
                imagedestroy($dummyImage);
            }

            // 8. Construir objeto de Pago
            $pagoPayload = [
                'type' => null,
                'compania' => $drugstore,
                'clientes' => [$clientCode],
                'documentos' => $documentosPayload,
                'listaPagos' => [
                    [
                        'Identificacion' => $idNumber,
                        'Referencia' => $ref9,
                        'FechaPago' => $formattedPaymentDate,
                        'CuentaBanco' => $cleanDestinationBank,
                        'MontoPago' => $finalPaymentAmount,
                        'TipoPersona' => strtoupper($idType),
                        'MontoDivisa' => 0,
                        'TasaDivisa' => 0,
                        'MedioPago' => 3, // Transferencia
                        'CodigoDivisa' => 'VES',
                        'TitularCuenta' => 'FARMACIA BARRIO SUCRE 2024, C.A',
                        'CorreoOrigen' => '',
                    ]
                ]
            ];

            // 9. Enviar petición multipart a la API oficial de Cobeca
            $submitRes = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->withoutVerifying()
            ->attach('Imagen', $fileContent, $fileName)
            ->post('https://sic.drogueriascobeca.com/api/pago/pagar', [
                'Pago' => json_encode($pagoPayload),
            ]);

            $jsonRes = $submitRes->json();
            Log::info('[COBECA PAYMENT] Respuesta al procesar pago: ' . json_encode($jsonRes));

            if ($submitRes->successful() && !empty($jsonRes['success'])) {
                return [
                    'success' => true,
                    'message' => $jsonRes['message'] ?? 'Pago registrado exitosamente en el portal de Cobeca / Mafarta.',
                    'data' => $jsonRes,
                ];
            }

            return [
                'success' => false,
                'message' => $jsonRes['message'] ?? 'No se pudo completar el procesamiento del pago en Cobeca.',
                'data' => $jsonRes,
            ];
        } catch (\Throwable $e) {
            Log::error('[COBECA PAYMENT] Excepción reportando pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Error de comunicación al reportar el pago: ' . $e->getMessage(),
            ];
        }
    }
}
