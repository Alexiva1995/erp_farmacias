<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\PaymentVerificationStatusEnum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\CreditPaymentAllocation;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterTenantB2BBridgeService
{
    /**
     * Sincroniza un pago o retención cargado en una Farmacia (tenant cliente)
     * hacia el tenant de la Droguería (mayorista B2B).
     */
    public function syncPaymentFromPharmacyToDrogueria(InvoicePayment $pharmacyPayment, array $invoiceIds, ?string $drogueriaTenantId = null): ?int
    {
        try {
            $pharmacyTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

            // Determinar el tenant de la Droguería
            if (!$drogueriaTenantId) {
                $firstInvoice = Invoice::with('supplier')->find($invoiceIds[0] ?? null);
                $drogueriaTenantId = $firstInvoice?->source_drogueria_tenant_id 
                    ?? $firstInvoice?->supplier?->provider_tenant_id;
            }

            if (!$drogueriaTenantId) {
                return null;
            }

            $drogueriaTenant = Tenant::find($drogueriaTenantId);
            if (!$drogueriaTenant) {
                Log::warning("[InterTenantBridge] Tenant droguería '{$drogueriaTenantId}' no encontrado.");
                return null;
            }

            $paymentData = [
                'amount' => (float) $pharmacyPayment->amount,
                'source_amount' => (float) ($pharmacyPayment->source_amount ?? $pharmacyPayment->amount),
                'source_currency' => $pharmacyPayment->source_currency ?? $pharmacyPayment->payment_method ?? 'USD',
                'payment_date' => $pharmacyPayment->payment_date,
                'reference' => $pharmacyPayment->reference,
                'photo_url' => $pharmacyPayment->photo_url,
                'external_tenant_id' => $pharmacyTenantId,
                'external_invoice_payment_id' => $pharmacyPayment->id,
            ];

            // Obtener números de factura de compra en la farmacia para hacer match en la droguería
            $invoiceNumbers = Invoice::whereIn('id', $invoiceIds)->pluck('invoice_number')->toArray();

            $createdPaymentId = null;

            // Ejecutar en el contexto de la Droguería
            $drogueriaTenant->run(function () use ($paymentData, $invoiceNumbers, $pharmacyTenantId, &$createdPaymentId) {
                // Ubicar la farmacia en los clientes de la droguería por linked_tenant_id
                $drogueriaClient = Client::where('linked_tenant_id', $pharmacyTenantId)->first();
                if (!$drogueriaClient) {
                    Log::warning("[InterTenantBridge] No se encontró cliente vinculado con linked_tenant_id={$pharmacyTenantId} en droguería.");
                    return;
                }

                $creditPayment = CreditPayment::create([
                    'client_id' => $drogueriaClient->id,
                    'seller_id' => $drogueriaClient->sales_rep_id ?: 1,
                    'payment_date' => $paymentData['payment_date'],
                    'method_Payment' => [
                        [
                            'method' => 'erp_tenant_sync',
                            'amount' => $paymentData['source_amount'],
                            'currency' => $paymentData['source_currency'],
                            'reference' => $paymentData['reference'],
                        ]
                    ],
                    'status' => PaymentVerificationStatusEnum::PENDING_VERIFICATION->value,
                    'reference_number' => $paymentData['reference'],
                    'receipt_photo_url' => $paymentData['photo_url'],
                    'amount_usd_equivalent' => $paymentData['amount'],
                    'source_channel' => 'erp_tenant_sync',
                    'external_tenant_id' => $paymentData['external_tenant_id'],
                    'external_invoice_payment_id' => $paymentData['external_invoice_payment_id'],
                ]);

                $createdPaymentId = $creditPayment->id;

                // Vincular con los créditos correspondientes en la droguería
                if (!empty($invoiceNumbers)) {
                    $matchedCredits = Credit::where('client_id', $drogueriaClient->id)
                        ->whereIn('invoice_number', $invoiceNumbers)
                        ->where('status', 'Active')
                        ->get();

                    foreach ($matchedCredits as $credit) {
                        CreditPaymentAllocation::create([
                            'credit_payment_id' => $creditPayment->id,
                            'credit_id' => $credit->id,
                            'amount_allocated_usd' => min($paymentData['amount'], (float) $credit->pending_amount),
                            'amount_allocated_original' => min($paymentData['source_amount'], (float) $credit->pending_amount),
                            'currency' => $paymentData['source_currency'],
                        ]);
                    }
                }
            });

            // Actualizar estado en la farmacia
            if ($createdPaymentId) {
                $pharmacyPayment->update([
                    'sync_status' => 'synced',
                    'remote_credit_payment_id' => $createdPaymentId,
                ]);
            }

            return $createdPaymentId;
        } catch (\Throwable $e) {
            Log::error("[InterTenantBridge] Error sincronizando pago a droguería: " . $e->getMessage(), [
                'payment_id' => $pharmacyPayment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Sincroniza una Factura de Venta emitida por la Droguería
     * hacia el tenant de la Farmacia (creando la factura de compra precargada).
     */
    public function syncInvoiceFromDrogueriaToPharmacy(Order $wholesaleOrder, Client $drogueriaClient): ?int
    {
        try {
            $pharmacyTenantId = $drogueriaClient->linked_tenant_id;
            if (!$pharmacyTenantId) {
                return null;
            }

            $pharmacyTenant = Tenant::find($pharmacyTenantId);
            if (!$pharmacyTenant) {
                return null;
            }

            $drogueriaTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

            // Cargar detalles de la orden con productos y lotes de la droguería
            $wholesaleOrder->load(['details.product', 'details.lot']);

            $createdInvoiceId = null;

            $pharmacyTenant->run(function () use ($wholesaleOrder, $drogueriaTenantId, &$createdInvoiceId) {
                // Ubicar el proveedor correspondiente a esta droguería
                $supplier = Supplier::where('provider_tenant_id', $drogueriaTenantId)->first();
                $supplierId = $supplier ? $supplier->id : 1;

                $invoiceNumber = 'FAC-' . str_pad((string) $wholesaleOrder->id, 8, '0', STR_PAD_LEFT);

                // Evitar duplicados
                $existing = Invoice::where('supplier_id', $supplierId)
                    ->where('invoice_number', $invoiceNumber)
                    ->first();

                if ($existing) {
                    $createdInvoiceId = $existing->id;
                    return;
                }

                $dueDate = Carbon::now()->addDays($wholesaleOrder->credit_term_days ?: 0);

                $invoice = Invoice::create([
                    'supplier_id' => $supplierId,
                    'source_drogueria_tenant_id' => $drogueriaTenantId,
                    'source_drogueria_order_id' => $wholesaleOrder->id,
                    'invoice_number' => $invoiceNumber,
                    'control_number' => 'CTRL-' . str_pad((string) $wholesaleOrder->id, 8, '0', STR_PAD_LEFT),
                    'created_invoice_date' => $wholesaleOrder->order_date,
                    'received_date' => Carbon::now(),
                    'exp_date' => $dueDate,
                    'currency' => 'USD',
                    'is_indexed' => true,
                    'status' => 'loaded',
                    'taxable_base' => $wholesaleOrder->taxable_base,
                    'total_amount' => $wholesaleOrder->total_amount,
                    'total_usd' => $wholesaleOrder->total_amount_usd,
                    'net_payable_amount' => $wholesaleOrder->total_amount,
                ]);

                // Crear los renglones con los lotes y vencimientos precargados
                foreach ($wholesaleOrder->details as $detail) {
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $detail->product_id,
                        'quantity' => $detail->quantity,
                        'price' => $detail->price,
                        'total' => round($detail->price * $detail->quantity, 2),
                    ]);
                }

                $createdInvoiceId = $invoice->id;
            });

            return $createdInvoiceId;
        } catch (\Throwable $e) {
            Log::error("[InterTenantBridge] Error sincronizando factura a farmacia: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Confirma la aprobación de un pago en el tenant de la Farmacia
     * cuando el Analista de Cobranzas de la Droguería lo aprueba.
     */
    public function confirmPaymentVerificationInPharmacy(CreditPayment $drogueriaPayment): bool
    {
        try {
            $pharmacyTenantId = $drogueriaPayment->external_tenant_id;
            $pharmacyPaymentId = $drogueriaPayment->external_invoice_payment_id;

            if (!$pharmacyTenantId || !$pharmacyPaymentId) {
                return false;
            }

            $pharmacyTenant = Tenant::find($pharmacyTenantId);
            if (!$pharmacyTenant) {
                return false;
            }

            $pharmacyTenant->run(function () use ($pharmacyPaymentId) {
                InvoicePayment::where('id', $pharmacyPaymentId)->update([
                    'sync_status' => 'verified_by_drogueria',
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error("[InterTenantBridge] Error confirmando pago en farmacia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra el rechazo de un pago en el tenant de la Farmacia.
     */
    public function rejectPaymentVerificationInPharmacy(CreditPayment $drogueriaPayment, string $reason): bool
    {
        try {
            $pharmacyTenantId = $drogueriaPayment->external_tenant_id;
            $pharmacyPaymentId = $drogueriaPayment->external_invoice_payment_id;

            if (!$pharmacyTenantId || !$pharmacyPaymentId) {
                return false;
            }

            $pharmacyTenant = Tenant::find($pharmacyTenantId);
            if (!$pharmacyTenant) {
                return false;
            }

            $pharmacyTenant->run(function () use ($pharmacyPaymentId, $reason) {
                InvoicePayment::where('id', $pharmacyPaymentId)->update([
                    'sync_status' => 'rejected_by_drogueria',
                    'rejection_reason' => $reason,
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error("[InterTenantBridge] Error registrando rechazo en farmacia: " . $e->getMessage());
            return false;
        }
    }
}
