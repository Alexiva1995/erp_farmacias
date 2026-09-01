<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use App\Models\Invoice;
use App\Models\Supplier;
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
            $expDate = !empty($doc['fechVenc']) ? Carbon::parse($doc['fechVenc'])->format('Y-m-d') : null;
            $emisionDate = !empty($doc['fechEmision']) ? Carbon::parse($doc['fechEmision'])->format('Y-m-d') : null;

            // Indexada si el portal marca facturaDolari = 1 o si la fecha de vencimiento es hoy o pasada
            $isIndexed = (isset($doc['facturaDolari']) && (int) $doc['facturaDolari'] === 1)
                || ($expDate && $expDate <= $today);

            if (empty($cleanNumber)) {
                $skippedCount++;
                continue;
            }

            // Buscar la factura en el ERP por múltiples variantes
            $possibleNumbers = array_unique([
                $cleanNumber,
                $rawDocNum,
                str_pad($cleanNumber, 8, '0', STR_PAD_LEFT),
                str_pad($cleanNumber, 10, '0', STR_PAD_LEFT),
            ]);

            $invoice = Invoice::where('supplier_id', $supplierId)
                ->where(function ($query) use ($possibleNumbers) {
                    $query->whereIn('invoice_number', $possibleNumbers);
                })
                ->first();

            // Consultar detalle completo en el API si falta nroControl o detalles
            $controlNumber = $doc['nroControl'] ?? null;
            $ndRefAmount = (float) ($doc['montoDifer'] ?? 0); // Diferencial cambiario en Bs
            $totalAmount = (float) ($doc['montoTotal'] ?? $doc['montoDoc'] ?? 0);
            $totalUsd = (float) ($doc['montoTotal2'] ?? 0);
            $exchangeRate = (float) ($doc['montoTasaConv'] ?? $doc['montoTasaFact'] ?? 0);

            if ($token && empty($controlNumber)) {
                $detailData = $this->getInvoiceDetail($rawDocNum, $token);
                if ($detailData) {
                    $controlNumber = $detailData['nroControl'] ?? $controlNumber;
                    if (empty($expDate) && !empty($detailData['fechaVencimiento'])) {
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

                // Actualizar fecha de vencimiento y de pago
                if ($expDate) {
                    $updateData['exp_date'] = $expDate;
                    if (empty($invoice->payment_date)) {
                        $updateData['payment_date'] = $expDate;
                    }
                }

                if ($emisionDate && empty($invoice->created_invoice_date)) {
                    $updateData['created_invoice_date'] = $emisionDate;
                }

                // Actualizar estado de indexación
                $updateData['is_indexed'] = $isIndexed;

                // Monto referencial / diferencial si aplica
                if ($ndRefAmount > 0) {
                    $updateData['nd_referential_amount'] = $ndRefAmount;
                }

                if ($totalUsd > 0 && ((float) ($invoice->total_usd ?? 0) <= 0)) {
                    $updateData['total_usd'] = $totalUsd;
                }

                if ($exchangeRate > 0 && ((float) ($invoice->exchange_rate ?? 0) <= 0)) {
                    $updateData['exchange_rate'] = $exchangeRate;
                }

                // Generar y almacenar el PDF de la factura si no tiene invoice_photo
                if (empty($invoice->invoice_photo) && $token) {
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
                    'total_usd' => (float) ($invoice->total_usd ?: ($exchangeRate > 0 ? round($totalAmount / $exchangeRate, 2) : 0)),
                    'nd_referential_amount' => $ndRefAmount,
                    'has_pdf' => !empty($invoice->invoice_photo) || !empty($updateData['invoice_photo']),
                ];
            } else {
                // Si la factura no existe en el ERP, crearla automáticamente
                $calcUsd = $totalUsd > 0 ? $totalUsd : ($exchangeRate > 0 ? round($totalAmount / $exchangeRate, 2) : 0);
                $newInvoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'invoice_number' => $rawDocNum,
                    'control_number' => $controlNumber,
                    'created_invoice_date' => $emisionDate ?: $today,
                    'exp_date' => $expDate ?: $today,
                    'payment_date' => $expDate ?: $today,
                    'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.00,
                    'exempt_amount' => (float) ($doc['montoExento'] ?? 0),
                    'taxable_base' => (float) ($doc['montoBase'] ?? ($totalAmount - ($doc['montoExento'] ?? 0))),
                    'tax_amount' => (float) ($doc['montoIva'] ?? 0),
                    'total_amount' => $totalAmount,
                    'total_usd' => $calcUsd,
                    'is_indexed' => $isIndexed,
                    'status' => 'pending',
                    'status_payment' => 0,
                    'uploaded_by' => $userId,
                    'registered_by' => $userId,
                ]);

                $createdCount++;
                $processed[] = [
                    'invoice_number' => $newInvoice->invoice_number,
                    'action' => 'created',
                    'control_number' => $newInvoice->control_number,
                    'exp_date' => $expDate,
                    'is_indexed' => $isIndexed,
                    'total_usd' => $calcUsd,
                    'nd_referential_amount' => $ndRefAmount,
                    'has_pdf' => false,
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
            $loginRes = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withoutVerifying()->timeout(30)->post(self::LOGIN_URL, [
                'User' => $username,
                'Password' => $password,
            ]);

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
            $documents = $data['documentos'] ?? [];

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

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.mafarta_invoice', [
                'invoice' => $invoice,
                'detail' => $detailData,
            ])->setPaper('a4', 'portrait');

            $pdfFileName = "invoice_mafarta_{$invoice->invoice_number}_" . time() . ".pdf";
            $pdfStorageRelPath = "invoices/{$pdfFileName}";

            \Illuminate\Support\Facades\Storage::disk('public')->put($pdfStorageRelPath, $pdf->output());

            return $pdfStorageRelPath;
        } catch (\Throwable $e) {
            Log::warning("[MAFARTA SCRAPER] Error generando PDF para factura #{$invoice->invoice_number}: " . $e->getMessage());
            return null;
        }
    }
}
