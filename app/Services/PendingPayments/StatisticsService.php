<?php

declare(strict_types=1);

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * Obtener estadísticas de pagos pendientes
     */
    public function getStatistics(): array
    {
        $baseQuery = Invoice::whereIn('status', ['pending', 'loaded', 'to_order'])
            ->whereNotNull('payment_date')
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            });

        $totalPending = $baseQuery->count();
        $totalAmount = $baseQuery->sum('total_amount');

        // Obtener facturas con cálculo dinámico de USD
        $invoices = $baseQuery->get();
        $invoices->transform(function ($invoice) {
            $invoice->total_usd = $this->calculateUSD($invoice->total_amount, $invoice->currency);
            return $invoice;
        });

        // Calcular totales por moneda con USD dinámico
        $byCurrency = $invoices->groupBy('currency')->map(function ($group, $currency) {
            return [
                'currency' => $currency,
                'total' => $group->sum('total_amount'),
                'total_usd' => $group->sum('total_usd'),
                'count' => $group->count()
            ];
        })->values();

        $bySupplier = Invoice::with('supplier')
            ->whereIn('status', ['pending', 'loaded', 'to_order'])
            ->whereNotNull('payment_date')
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            })
            ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('supplier_id')
            ->get();

        // Calcular facturas vencidas
        $overdueInvoices = Invoice::whereIn('status', ['pending', 'loaded', 'to_order'])
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            })
            ->where(function ($q) {
                $q->whereDate('payment_date', '<', Carbon::now())
                    ->orWhereDate('exp_date', '<', Carbon::now());
            })
            ->count();

        // Calcular totales por moneda para el frontend
        $totalsByCurrency = [
            'bs' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
            'usd' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
            'cop' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
            'usd_converted' => 0
        ];

        foreach ($invoices as $invoice) {
            $currency = strtolower($invoice->currency);
            if (isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency]['amount'] += $invoice->total_amount;
                $totalsByCurrency[$currency]['count']++;
                $totalsByCurrency[$currency]['total_usd'] += $invoice->total_usd;
            }
            $totalsByCurrency['usd_converted'] += $invoice->total_usd;
        }

        return [
            'total_pending_invoices' => $totalPending,
            'total_amount_pending' => $totalAmount,
            'overdue_invoices' => $overdueInvoices,
            'by_currency' => $byCurrency,
            'by_supplier' => $bySupplier,
            'totals_by_currency' => $totalsByCurrency
        ];
    }

    /**
     * Calcular monto en USD usando la tabla exchange_rates
     */
    private function calculateUSD($amount, $currency)
    {
        if ($currency === 'USD') {
            return (float) $amount;
        }

        // Mapear moneda para buscar en exchange_rates
        $currencyCode = $currency === 'Bs' ? 'BS' : $currency;

        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (!$exchangeRate) {
            return 0;
        }

        return round((float) $amount / (float) $exchangeRate->rate, 2);
    }
}
