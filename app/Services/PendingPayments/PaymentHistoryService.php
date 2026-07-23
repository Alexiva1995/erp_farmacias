<?php

declare(strict_types=1);

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentHistoryService
{
    /**
     * Obtener historial de pagos con filtros
     */
    public function getPaymentHistory(array $filters = []): Collection
    {
        $query = InvoicePayment::with(['invoices.supplier', 'user'])
            ->orderBy('created_at', 'desc');

        // Aplicar filtros
        if (isset($filters['supplier_id'])) {
            $query->whereHas('invoices', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }

        if (isset($filters['currency'])) {
            $query->whereHas('invoices', function ($q) use ($filters) {
                $q->where('currency', $filters['currency']);
            });
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('invoices', function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(15);
    }

    /**
     * Transformar pagos para el frontend
     */
    public function transformPayments(Collection $payments): Collection
    {
        return $payments->getCollection()->transform(function ($payment) {
            $firstInvoice = $payment->invoices->first();
            $payment->currency = $payment->payment_method;

            // Calcular el equivalente en USD
            if ($payment->payment_method === 'USD') {
                $payment->amount_usd = $payment->amount;
            } else {
                $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                if ($exchangeRate) {
                    $payment->amount_usd = round($payment->amount / $exchangeRate->rate, 2);
                } else {
                    $payment->amount_usd = 0;
                }
            }

            // Determinar si es pago completo o parcial
            $totalInvoiceAmount = 0;
            foreach ($payment->invoices as $invoice) {
                $totalInvoiceAmount += $invoice->total_usd;
            }
            $payment->payment_type = $payment->amount_usd >= $totalInvoiceAmount ? 'full' : 'partial';
            $payment->invoice_total_usd = $totalInvoiceAmount;

            // Calcular información de pagos para facturas con pagos parciales
            if ($payment->payment_type === 'partial') {
                $this->calculatePartialPaymentInfo($payment);
            }

            return $payment;
        });
    }

    /**
     * Calcular información de pagos parciales
     */
    private function calculatePartialPaymentInfo($payment): void
    {
        $invoiceIds = $payment->invoices->pluck('id');

        // Obtener todos los pagos para estas facturas
        $allPayments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->get();

        $totalPaidUSD = 0;
        foreach ($allPayments as $p) {
            if ($p->payment_method === 'USD') {
                $totalPaidUSD += $p->amount;
            } else {
                $exchangeRate = ExchangeRate::where('currency_code', $p->payment_method)->first();
                if ($exchangeRate) {
                    $totalPaidUSD += round($p->amount / $exchangeRate->rate, 2);
                }
            }
        }

        $payment->total_paid_usd = $totalPaidUSD;
        $payment->remaining_amount_usd = max(0, $payment->invoice_total_usd - $totalPaidUSD);
        $payment->payment_percentage = $payment->invoice_total_usd > 0 ?
            round(($totalPaidUSD / $payment->invoice_total_usd) * 100, 2) : 0;
    }

    /**
     * Obtener información de pagos para facturas específicas
     */
    public function getPaidAmountInfo(array $invoiceIds): array
    {
        // Obtener el total ya pagado en USD para estas facturas
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->get();

        $totalPaidUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $totalPaidUSD += $payment->amount;
            } else {
                $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                if ($exchangeRate) {
                    $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Obtener el total de las facturas en USD
        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        $totalInvoiceUSD = $invoices->sum('total_usd');

        // Calcular monto restante
        $remainingAmount = max(0, $totalInvoiceUSD - $totalPaidUSD);

        // Determinar si hay pagos previos
        $hasPreviousPayments = $totalPaidUSD > 0;

        // Determinar el estado de pago
        $paymentStatus = 'unpaid';
        if ($totalPaidUSD >= $totalInvoiceUSD) {
            $paymentStatus = 'paid';
        } elseif ($totalPaidUSD > 0) {
            $paymentStatus = 'partial';
        }

        return [
            'total_invoice_usd' => $totalInvoiceUSD,
            'total_paid_usd' => $totalPaidUSD,
            'remaining_amount' => $remainingAmount,
            'has_previous_payments' => $hasPreviousPayments,
            'payment_status' => $paymentStatus,
            'payment_percentage' => $totalInvoiceUSD > 0 ? round(($totalPaidUSD / $totalInvoiceUSD) * 100, 2) : 0
        ];
    }
}
