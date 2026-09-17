<?php

declare(strict_types=1);

namespace App\Services\PendingPayments;

use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentHistoryService
{
    /**
     * Construir consulta base con filtros
     */
    private function buildQuery(array $filters = []): Builder
    {
        $query = InvoicePayment::with(['invoices.supplier', 'invoices.payments', 'user'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['supplier_id'])) {
            $query->whereHas('invoices', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }

        if (!empty($filters['currency'])) {
            $query->whereHas('invoices', function ($q) use ($filters) {
                $q->where('currency', $filters['currency']);
            });
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($groupedQuery) use ($search) {
                $groupedQuery->whereHas('invoices', function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('control_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                })->orWhere('reference', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Obtener historial de pagos con filtros y paginación
     */
    public function getPaymentHistory(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters);

        if ($perPage === -1) {
            $perPage = $query->count() ?: 1;
        }

        return $query->paginate($perPage);
    }

    /**
     * Obtener estadísticas de KPIs de pagos según filtros aplicados
     */
    public function getSummaryStats(array $filters = []): array
    {
        $payments = $this->buildQuery($filters)->get();
        $exchangeRates = ExchangeRate::all()->keyBy('currency_code');

        $totalTransactions = $payments->count();
        $totalUSD = 0.0;
        $totalVES = 0.0;
        $totalCOP = 0.0;

        foreach ($payments as $payment) {
            $method = strtoupper((string)$payment->payment_method);
            if ($method === 'USD') {
                $totalUSD += (float)$payment->amount;
            } elseif ($method === 'VES' || $method === 'BS') {
                $totalVES += (float)$payment->amount;
                $rateObj = $exchangeRates->get('VES') ?? $exchangeRates->get('BS');
                if ($rateObj && $rateObj->rate > 0) {
                    $totalUSD += round(((float)$payment->amount) / $rateObj->rate, 2);
                }
            } else {
                $rateObj = $exchangeRates->get($method);
                if ($rateObj && $rateObj->rate > 0) {
                    $totalUSD += round(((float)$payment->amount) / $rateObj->rate, 2);
                }
            }
        }

        $averageUSD = $totalTransactions > 0 ? round($totalUSD / $totalTransactions, 2) : 0.0;

        return [
            'total_transactions' => $totalTransactions,
            'total_usd' => round($totalUSD, 2),
            'total_ves' => round($totalVES, 2),
            'average_usd' => $averageUSD,
        ];
    }

    /**
     * Transformar pagos de forma eficiente precargando la tabla de tasas de cambio
     */
    public function transformPayments(LengthAwarePaginator $payments): void
    {
        $exchangeRates = ExchangeRate::all()->keyBy('currency_code');

        $payments->getCollection()->transform(function ($payment) use ($exchangeRates) {
            $payment->currency = $payment->payment_method;

            // Monto de liquidación al proveedor convertido a USD
            if ($payment->payment_method === 'USD') {
                $payment->amount_usd = (float) $payment->amount;
            } else {
                $rateKey = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                $rateObj = $exchangeRates->get($rateKey) ?? $exchangeRates->get($payment->payment_method);
                if ($rateObj && $rateObj->rate > 0) {
                    $payment->amount_usd = round($payment->amount / $rateObj->rate, 2);
                } else {
                    $payment->amount_usd = 0;
                }
            }

            // Si hay un monto de egreso origen (ej. cambista COP)
            if (!empty($payment->source_amount) && !empty($payment->source_currency)) {
                if ($payment->source_currency === 'USD') {
                    $payment->source_amount_usd = (float) $payment->source_amount;
                } else {
                    $srcRateKey = ($payment->source_currency === 'COP') ? 'COPC' : $payment->source_currency;
                    $srcRateObj = $exchangeRates->get($srcRateKey) ?? $exchangeRates->get($payment->source_currency);
                    if ($srcRateObj && $srcRateObj->rate > 0) {
                        $payment->source_amount_usd = round($payment->source_amount / $srcRateObj->rate, 2);
                    } else {
                        $payment->source_amount_usd = $payment->amount_usd;
                    }
                }
            } else {
                $payment->source_amount = (float) $payment->amount;
                $payment->source_currency = $payment->currency;
                $payment->source_amount_usd = $payment->amount_usd;
            }

            $totalInvoiceAmount = 0;
            $totalInvoiceBs = 0;
            $allInvoicesPaid = true;
            $hasInvoices = $payment->invoices->isNotEmpty();

            $bcvRateObj = $exchangeRates->get('VES') ?? $exchangeRates->get('BS');
            $currentBcvRate = ($bcvRateObj && $bcvRateObj->rate > 0) ? (float)$bcvRateObj->rate : 1.0;

            foreach ($payment->invoices as $invoice) {
                $invUsd = (float) $invoice->total_usd;
                $totalInvoiceAmount += $invUsd;

                // Si la factura es indexada o su moneda es USD, calcular su deuda real indexada al BCV
                if ($invoice->is_indexed || $invoice->currency === 'USD') {
                    $rateToUse = ((float)$invoice->exchange_rate > 0) ? (float)$invoice->exchange_rate : $currentBcvRate;
                    $invBs = round($invUsd * $rateToUse, 2);
                    $totalInvoiceBs += $invBs;
                    $invoice->calculated_amount_bs = $invBs;
                } elseif ($invoice->currency === 'Bs' || $invoice->currency === 'VES') {
                    $totalInvoiceBs += (float)$invoice->total_amount;
                    $invoice->calculated_amount_bs = (float)$invoice->total_amount;
                } else {
                    $invBs = (float)($invoice->total_amount_bs ?? round($invUsd * $currentBcvRate, 2));
                    $totalInvoiceBs += $invBs;
                    $invoice->calculated_amount_bs = $invBs;
                }

                if ((int) $invoice->status_payment !== 1) {
                    $allInvoicesPaid = false;
                }
            }

            if ($hasInvoices) {
                if ($allInvoicesPaid) {
                    // Si las facturas están completamente liquidadas (status_payment === 1)
                    $hasMultiplePayments = false;
                    foreach ($payment->invoices as $invoice) {
                        if ($invoice->relationLoaded('payments') && $invoice->payments->count() > 1) {
                            $hasMultiplePayments = true;
                            break;
                        }
                    }

                    if ($hasMultiplePayments && $totalInvoiceAmount > 0) {
                        // Si hubo múltiples pagos para la misma factura y este pago fue una fracción menor (abono previo)
                        $payment->payment_type = ($payment->amount_usd >= ($totalInvoiceAmount * 0.70)) ? 'full' : 'partial';
                    } else {
                        // Si es el pago único/definitivo y las facturas quedaron pagadas
                        $payment->payment_type = 'full';
                    }
                } else {
                    // Si la factura no está completamente saldada (status_payment !== 1), es un abono parcial
                    $payment->payment_type = 'partial';
                }
            } else {
                // Fallback si no tiene facturas asociadas directamente
                $payment->payment_type = ($payment->amount_usd >= ($totalInvoiceAmount - 0.05)) ? 'full' : 'partial';
            }

            $payment->invoice_total_usd = $totalInvoiceAmount;
            $payment->invoice_total_bs = $totalInvoiceBs > 0 ? $totalInvoiceBs : round($totalInvoiceAmount * $currentBcvRate, 2);

            return $payment;
        });
    }
}
