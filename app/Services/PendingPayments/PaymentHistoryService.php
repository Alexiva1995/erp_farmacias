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
        $query = InvoicePayment::with(['invoices.supplier', 'user'])
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

            if ($payment->payment_method === 'USD') {
                $payment->amount_usd = (float) $payment->amount;
            } else {
                $rateObj = $exchangeRates->get($payment->payment_method);
                if ($rateObj && $rateObj->rate > 0) {
                    $payment->amount_usd = round($payment->amount / $rateObj->rate, 2);
                } else {
                    $payment->amount_usd = 0;
                }
            }

            $totalInvoiceAmount = 0;
            foreach ($payment->invoices as $invoice) {
                $totalInvoiceAmount += (float) $invoice->total_usd;
            }
            $payment->payment_type = $payment->amount_usd >= $totalInvoiceAmount ? 'full' : 'partial';
            $payment->invoice_total_usd = $totalInvoiceAmount;

            return $payment;
        });
    }
}
