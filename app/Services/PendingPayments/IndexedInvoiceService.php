<?php

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\Expense;
use Illuminate\Support\Facades\Log;

class IndexedInvoiceService
{
    /**
     * Calcular monto indexado para facturas indexadas
     * Para facturas indexadas: Bs = USD × Tasa BCV actual
     */
    public function calculateIndexedAmount(Invoice $invoice): array
    {
        if (!$invoice->is_indexed || $invoice->currency !== 'Bs') {
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        // Obtener tasa BCV actual (BS)
        $exchangeRate = ExchangeRate::where('currency_code', 'BS')->first();
        if (!$exchangeRate) {
            // Si no hay tasa BCV, usar la tasa original de la factura
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        // Calcular monto indexado: USD × Tasa BCV actual
        $indexedAmountBs = round($invoice->total_usd * $exchangeRate->rate, 2);

        return [
            'original_amount' => $invoice->total_amount,
            'original_amount_usd' => $invoice->total_usd,
            'indexed_amount' => $indexedAmountBs,
            'indexed_amount_usd' => $invoice->total_usd, // El USD sigue siendo el mismo
            'is_indexed' => true,
            'bcv_rate' => $exchangeRate->rate,
            'rate_date' => $exchangeRate->updated_at
        ];
    }

    /**
     * Cambiar estado de indexación de una factura
     */
    public function toggleIndexedStatus(int $invoiceId, bool $isIndexed): array
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update(['is_indexed' => $isIndexed]);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'is_indexed' => $invoice->is_indexed,
            'message' => $isIndexed ? 'Factura marcada como indexada' : 'Factura desmarcada como indexada'
        ];
    }

    /**
     * Calcular montos restantes para facturas indexadas
     */
    public function calculateIndexedRemainingAmounts(Invoice $invoice): array
    {
        if ($invoice->is_indexed && $invoice->currency === 'Bs') {
            // Para facturas indexadas: USD siempre fijo, Bs se calcula dinámicamente
            $invoiceRemainingUSD = $invoice->total_usd; // USD fijo

            // Calcular Bs usando la tasa BCV actual
            $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
            if ($bcvRate) {
                $invoiceRemainingOriginal = round($invoice->total_usd * $bcvRate->rate, 2);
            } else {
                $invoiceRemainingOriginal = $invoice->total_amount; // Fallback
            }
        } else {
            // Para facturas no indexadas: cálculo normal
            $invoiceRemainingUSD = $invoice->total_usd;
            $invoiceRemainingOriginal = $invoice->total_amount;

            // Si hay pagos parciales, calcular el monto restante individual
            $invoicePayments = \App\Models\InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
                $query->where('id', $invoice->id);
            })->get();

            if ($invoicePayments->count() > 0) {
                $totalPaidUSD = 0;
                foreach ($invoicePayments as $payment) {
                    if ($payment->payment_method === 'USD') {
                        $totalPaidUSD += $payment->amount;
                    } else {
                        $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                        if ($exchangeRate) {
                            $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                        }
                    }
                }

                $invoiceRemainingUSD = max(0, $invoice->total_usd - $totalPaidUSD);

                // Convertir a moneda original
                if ($invoice->currency === 'Bs') {
                    $exchangeRate = ExchangeRate::where('currency_code', 'VES')->first();
                    if ($exchangeRate) {
                        $invoiceRemainingOriginal = round($invoiceRemainingUSD * $exchangeRate->rate, 2);
                    }
                } elseif ($invoice->currency === 'COP') {
                    $exchangeRate = ExchangeRate::where('currency_code', 'COP')->first();
                    if ($exchangeRate) {
                        $invoiceRemainingOriginal = round($invoiceRemainingUSD * $exchangeRate->rate, 2);
                    }
                } else {
                    $invoiceRemainingOriginal = $invoiceRemainingUSD;
                }
            }
        }

        return [
            'remaining_usd' => $invoiceRemainingUSD,
            'remaining_original' => $invoiceRemainingOriginal
        ];
    }
}
