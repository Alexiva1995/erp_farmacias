<?php

declare(strict_types=1);

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PendingPaymentsService
{
    /**
     * Obtener facturas pendientes con filtros aplicados
     */
    public function getPendingInvoices(array $filters = []): Collection
    {
        $query = Invoice::with(['supplier', 'payments'])
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            });

        $currentYearStart = Carbon::now()->startOfYear();
        $query->whereDate('payment_date', '>=', $currentYearStart);

        $query->orderBy('payment_date', 'asc');

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['show_overdue_only']) && filter_var($filters['show_overdue_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where(function ($q) {
                $dueDate = Carbon::now();
                $q->whereDate('payment_date', '<=', $dueDate)
                    ->orWhereDate('exp_date', '<', Carbon::now());
            });
        }

        return $query->get();
    }

    /**
     * Agrupar y formatear facturas sin N+1 en proveedores o tasas
     */
    public function getGroupedPendingPayments(Collection $invoices): Collection
    {
        $exchangeRates = ExchangeRate::all()->keyBy('currency_code');
        $vesRate = $exchangeRates->get('VES')?->rate ?? 1;
        $copRate = $exchangeRates->get('COP')?->rate ?? 1;
        $bcvRateVal = $exchangeRates->get('BS')?->rate ?? 1;

        return $invoices->groupBy(function ($invoice) {
            return $invoice->supplier_id . '_' . $invoice->payment_date;
        })->map(function ($group) use ($exchangeRates, $vesRate, $copRate, $bcvRateVal) {
            $firstInvoice = $group->first();

            $totalAmountUSD = $group->sum('total_usd');
            $totalAmountOriginal = $group->sum('total_amount');

            $remainingAmountUSD = $totalAmountUSD;
            $remainingAmountOriginal = $totalAmountOriginal;

            $payments = $group->flatMap(function ($invoice) {
                return $invoice->payments;
            })->unique('id');

            if ($payments->count() > 0) {
                $totalPaidUSD = 0;
                foreach ($payments as $payment) {
                    if ($payment->payment_method === 'USD') {
                        $totalPaidUSD += $payment->amount;
                    } else {
                        $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                        $rateObj = $exchangeRates->get($rateCurrency);
                        if ($rateObj && $rateObj->rate > 0) {
                            $totalPaidUSD += round($payment->amount / $rateObj->rate, 2);
                        }
                    }
                }

                $remainingAmountUSD = max(0, $totalAmountUSD - $totalPaidUSD);

                if ($firstInvoice->currency === 'Bs') {
                    $remainingAmountOriginal = round($remainingAmountUSD * $vesRate, 2);
                } elseif ($firstInvoice->currency === 'COP') {
                    $remainingAmountOriginal = round($remainingAmountUSD * $copRate, 2);
                } else {
                    $remainingAmountOriginal = $remainingAmountUSD;
                }
            }

            $supplierPreferredCurrency = $this->getSupplierPreferredCurrencyFromGroup($group, $firstInvoice->supplier);
            $totalInSupplierCurrency = $this->calculateTotalInSupplierCurrencyFromGroup($group, $supplierPreferredCurrency, $exchangeRates);

            return [
                'supplier_id' => $firstInvoice->supplier_id,
                'supplier_name' => $firstInvoice->supplier?->name ?? 'N/A',
                'payment_date' => $firstInvoice->payment_date,
                'currency' => $firstInvoice->currency,
                'total_amount' => $remainingAmountOriginal,
                'total_usd' => $totalAmountUSD,
                'remainingAmountUSD' => $remainingAmountUSD,
                'total_in_supplier_currency' => $totalInSupplierCurrency,
                'supplier_preferred_currency' => $supplierPreferredCurrency,
                'invoice_count' => $group->count(),
                'invoices' => $group->map(function ($invoice) use ($totalInSupplierCurrency, $totalAmountUSD, $exchangeRates, $vesRate, $copRate, $bcvRateVal) {
                    $indexedData = $this->calculateIndexedAmountData($invoice, $exchangeRates);

                    if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                        $invoiceRemainingUSD = $invoice->total_usd;
                        $invoiceRemainingOriginal = round($invoice->total_usd * $bcvRateVal, 2);
                    } else {
                        $invoiceRemainingUSD = $invoice->total_usd;
                        $invoiceRemainingOriginal = $invoice->total_amount;

                        $invoicePayments = $invoice->payments;

                        if ($invoicePayments->count() > 0) {
                            $totalPaidUSD = 0;
                            foreach ($invoicePayments as $payment) {
                                if ($payment->payment_method === 'USD') {
                                    $totalPaidUSD += $payment->amount;
                                } else {
                                    $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                                    $rateObj = $exchangeRates->get($rateCurrency);
                                    if ($rateObj && $rateObj->rate > 0) {
                                        $totalPaidUSD += round($payment->amount / $rateObj->rate, 2);
                                    }
                                }
                            }

                            $invoiceRemainingUSD = max(0, $invoice->total_usd - $totalPaidUSD);

                            if ($invoice->currency === 'Bs') {
                                $invoiceRemainingOriginal = round($invoiceRemainingUSD * $vesRate, 2);
                            } elseif ($invoice->currency === 'COP') {
                                $invoiceRemainingOriginal = round($invoiceRemainingUSD * $copRate, 2);
                            } else {
                                $invoiceRemainingOriginal = $invoiceRemainingUSD;
                            }
                        }
                    }

                    $displayAmount = $indexedData['is_indexed'] ? $indexedData['indexed_amount'] : $invoiceRemainingOriginal;
                    $displayOriginalAmount = $indexedData['is_indexed'] ? $indexedData['indexed_amount'] : $invoice->total_amount;

                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'control_number' => $invoice->control_number ?? 'N/A',
                        'supplier_rif' => $invoice->supplier?->rif ?? 'N/A',
                        'total_amount' => $displayAmount,
                        'total_usd' => $invoice->total_usd,
                        'invoiceRemainingUSD' => $invoiceRemainingUSD,
                        'remaining_amount' => $invoiceRemainingOriginal,
                        'remaining_amount_usd' => $invoiceRemainingUSD,
                        'original_amount' => $displayOriginalAmount,
                        'original_amount_usd' => $invoice->total_usd,
                        'currency' => $invoice->currency,
                        'is_indexed' => $invoice->is_indexed ?? false,
                        'claim_amount' => (float) ($invoice->claim_amount ?? 0),
                        'nd_referential_amount' => (float) ($invoice->nd_referential_amount ?? 0),
                        'net_payable_amount' => $invoice->net_payable_amount !== null ? (float) $invoice->net_payable_amount : null,
                        'total_amount_discount' => $invoice->total_amount_discount !== null ? (float) $invoice->total_amount_discount : null,
                        'indexed_data' => $indexedData,
                        'exchange_rate' => $invoice->exchange_rate,
                        'exp_date' => $invoice->exp_date,
                        'payment_date' => $invoice->payment_date,
                        'invoice_photo' => $invoice->invoice_photo,
                        'pdf_url' => $invoice->invoice_photo ? asset('storage/' . $invoice->invoice_photo) : null,
                        'supplier_total_bs' => $totalInSupplierCurrency,
                        'supplier_total_usd' => $totalAmountUSD
                    ];
                })
            ];
        })->values();
    }

    /**
     * Determinar la moneda preferida del proveedor sin hacer subconsultas N+1
     */
    private function getSupplierPreferredCurrencyFromGroup(Collection $groupInvoices, ?Supplier $supplier): string
    {
        if (!$supplier) {
            return 'USD';
        }

        $supplierName = strtolower($supplier->name);
        if (strpos($supplierName, 'cristalmedicals') !== false) {
            return 'USD';
        }

        $currencyCounts = $groupInvoices->groupBy('currency')->map->count();
        return $currencyCounts->sortDesc()->keys()->first() ?? 'USD';
    }

    /**
     * Calcular total en moneda preferida del proveedor sin subconsultas
     */
    private function calculateTotalInSupplierCurrencyFromGroup(Collection $invoices, string $supplierCurrency, Collection $exchangeRates): float
    {
        $totalUSD = $invoices->sum('total_usd');

        if ($supplierCurrency === 'USD') {
            return round($totalUSD, 2);
        }

        $currencyCode = $supplierCurrency === 'Bs' ? 'BS' : $supplierCurrency;
        $exchangeRate = $exchangeRates->get($currencyCode);

        if (!$exchangeRate || $exchangeRate->rate <= 0) {
            return round($totalUSD, 2);
        }

        return round($totalUSD * $exchangeRate->rate, 2);
    }

    /**
     * Calcular los datos de indexación sin subconsultas
     */
    private function calculateIndexedAmountData(Invoice $invoice, Collection $exchangeRates): array
    {
        if (!$invoice->is_indexed || $invoice->currency !== 'Bs') {
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        $exchangeRate = $exchangeRates->get('BS');
        if (!$exchangeRate) {
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        $indexedAmountBs = round($invoice->total_usd * $exchangeRate->rate, 2);

        return [
            'original_amount' => $invoice->total_amount,
            'original_amount_usd' => $invoice->total_usd,
            'indexed_amount' => $indexedAmountBs,
            'indexed_amount_usd' => $invoice->total_usd,
            'is_indexed' => true,
            'bcv_rate' => $exchangeRate->rate,
            'rate_date' => $exchangeRate->updated_at
        ];
    }
}
