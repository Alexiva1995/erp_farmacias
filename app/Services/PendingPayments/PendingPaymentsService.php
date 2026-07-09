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
        // 🔍 LOG DEBUG: Inicio del servicio
        \Log::info('🔍 [DEBUG] PendingPaymentsService::getPendingInvoices - INICIO', [
            'filters_recibidos' => $filters,
            'filters_count' => count($filters),
            'timestamp' => now()->toDateTimeString()
        ]);

        $query = Invoice::with(['supplier'])
            ->whereIn('status', ['pending', 'loaded', 'to_order'])
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            });

        // 🔍 LOG DEBUG: Query base creada
        \Log::info('🔍 [DEBUG] Query base creada', [
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings()
        ]);

        // Aplicar filtros
        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
            \Log::info('🔍 [DEBUG] Filtro supplier_id aplicado', ['supplier_id' => $filters['supplier_id']]);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
            \Log::info('🔍 [DEBUG] Filtro start_date aplicado', ['start_date' => $filters['start_date']]);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
            \Log::info('🔍 [DEBUG] Filtro end_date aplicado', ['end_date' => $filters['end_date']]);
        }

        if (isset($filters['show_overdue_only']) && $filters['show_overdue_only']) {
            $query->where(function ($q) {
                $dueDate = Carbon::now()->subDay();
                $q->whereDate('payment_date', '<=', $dueDate)
                    ->orWhereDate('exp_date', '<', Carbon::now());
            });
            \Log::info('🔍 [DEBUG] Filtro show_overdue_only aplicado', [
                'show_overdue_only' => $filters['show_overdue_only'],
                'due_date' => Carbon::now()->subDay()->toDateString()
            ]);
        }

        // Ordenamiento fijo
        $query->orderByRaw('CASE 
            WHEN status = "to_order" THEN 0 
            WHEN status = "pending" THEN 1 
            ELSE 2 
        END')
            ->orderBy('payment_date', 'asc');

        // 🔍 LOG DEBUG: Query final antes de ejecutar
        \Log::info('🔍 [DEBUG] Query final antes de ejecutar', [
            'query_sql_final' => $query->toSql(),
            'query_bindings_final' => $query->getBindings()
        ]);

        $result = $query->get();

        // 🔍 LOG DEBUG: Resultado obtenido
        \Log::info('🔍 [DEBUG] Resultado obtenido del servicio', [
            'total_facturas' => $result->count(),
            'facturas_ids' => $result->pluck('id')->toArray(),
            'facturas_detalle' => $result->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'status_payment' => $invoice->status_payment,
                    'supplier_name' => $invoice->supplier->name ?? 'N/A'
                ];
            })
        ]);

        return $result;
    }

    /**
     * Agrupar facturas por proveedor y fecha
     */
    public function groupInvoicesBySupplierAndDate(Collection $invoices): Collection
    {
        return $invoices->groupBy(function ($invoice) {
            return $invoice->supplier_id . '_' . $invoice->payment_date;
        });
    }

    /**
     * Calcular totales por moneda
     */
    public function calculateTotalsByCurrency(Collection $invoices): array
    {
        $invoicesBs = $invoices->where('currency', 'Bs');
        $invoicesUsd = $invoices->where('currency', 'USD');
        $invoicesCop = $invoices->where('currency', 'COP');

        return [
            'bs' => [
                'amount' => $invoicesBs->sum('total_amount'),
                'count' => $invoicesBs->count(),
                'total_usd' => $invoicesBs->sum('total_usd')
            ],
            'usd' => [
                'amount' => $invoicesUsd->sum('total_amount'),
                'count' => $invoicesUsd->count(),
                'total_usd' => $invoicesUsd->sum('total_usd')
            ],
            'cop' => [
                'amount' => $invoicesCop->sum('total_amount'),
                'count' => $invoicesCop->count(),
                'total_usd' => $invoicesCop->sum('total_usd')
            ],
            'usd_converted' => $invoices->sum('total_usd')
        ];
    }

    /**
     * Determinar la moneda preferida del proveedor
     */
    public function getSupplierPreferredCurrency(Supplier $supplier): string
    {
        $supplierName = strtolower($supplier->name);

        // Cristalmedicals siempre es USD
        if (strpos($supplierName, 'cristalmedicals') !== false) {
            return 'USD';
        }

        // Para otros proveedores, determinar por las facturas pendientes
        $invoices = Invoice::where('supplier_id', $supplier->id)
            ->whereIn('status', ['pending', 'loaded', 'to_order'])
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            })
            ->get();

        if ($invoices->isEmpty()) {
            return 'USD'; // Default
        }

        // Contar facturas por moneda
        $currencyCounts = $invoices->groupBy('currency')->map->count();

        // Retornar la moneda más común
        return $currencyCounts->sortDesc()->keys()->first() ?? 'USD';
    }

    /**
     * Calcular total en moneda del proveedor considerando facturas indexadas
     */
    public function calculateTotalInSupplierCurrency(Collection $invoices, string $supplierCurrency): float
    {
        $totalUSD = 0;

        foreach ($invoices as $invoice) {
            // Para facturas indexadas en Bs, usar el monto indexado
            if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
                if ($bcvRate) {
                    $totalUSD += $invoice->total_usd; // USD fijo para facturas indexadas
                } else {
                    $totalUSD += $invoice->total_usd;
                }
            } else {
                // Para facturas no indexadas, usar el total_usd normal
                $totalUSD += $invoice->total_usd;
            }
        }

        // Si la moneda del proveedor es USD, retornar directamente
        if ($supplierCurrency === 'USD') {
            return round($totalUSD, 2);
        }

        // Convertir desde USD a la moneda del proveedor
        $currencyCode = $supplierCurrency === 'Bs' ? 'BS' : $supplierCurrency;
        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (!$exchangeRate) {
            return round($totalUSD, 2); // Fallback a USD
        }

        return round($totalUSD * $exchangeRate->rate, 2);
    }

    /**
     * Calcular montos restantes considerando pagos parciales
     */
    public function calculateRemainingAmounts(Collection $invoices): array
    {
        $invoiceIds = $invoices->pluck('id');
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->get();

        $totalAmountUSD = $invoices->sum('total_usd');
        $totalAmountOriginal = $invoices->sum('total_amount');
        $remainingAmountUSD = $totalAmountUSD;
        $remainingAmountOriginal = $totalAmountOriginal;

        if ($payments->count() > 0) {
            // Calcular total pagado en USD
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

            // Calcular monto restante
            $remainingAmountUSD = max(0, $totalAmountUSD - $totalPaidUSD);

            // Convertir monto restante a moneda original
            $firstInvoice = $invoices->first();
            if ($firstInvoice->currency === 'Bs') {
                $exchangeRate = ExchangeRate::where('currency_code', 'VES')->first();
                if ($exchangeRate) {
                    $remainingAmountOriginal = round($remainingAmountUSD * $exchangeRate->rate, 2);
                }
            } elseif ($firstInvoice->currency === 'COP') {
                $exchangeRate = ExchangeRate::where('currency_code', 'COP')->first();
                if ($exchangeRate) {
                    $remainingAmountOriginal = round($remainingAmountUSD * $exchangeRate->rate, 2);
                }
            } else {
                $remainingAmountOriginal = $remainingAmountUSD;
            }
        }

        return [
            'total_amount_usd' => $totalAmountUSD,
            'total_amount_original' => $totalAmountOriginal,
            'remaining_amount_usd' => $remainingAmountUSD,
            'remaining_amount_original' => $remainingAmountOriginal
        ];
    }
}
