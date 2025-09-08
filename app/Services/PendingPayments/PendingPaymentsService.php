<?php

namespace App\Services\PendingPayments;

use App\Models\Invoice;
use App\Models\ExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PendingPaymentsService
{
    /**
     * Obtener facturas pendientes agrupadas por proveedor y fecha
     */
    public function getGroupedPendingPayments(array $filters = []): Collection
    {
        $query = Invoice::with(['supplier'])
            ->whereIn('status', ['pending', 'to_order'])
            ->whereNotNull('payment_date');

        // Aplicar filtros
        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        $invoices = $query->orderBy('payment_date', 'asc')->get();

        return $this->groupInvoicesBySupplierAndDate($invoices);
    }

    /**
     * Agrupar facturas por proveedor y fecha de pago
     */
    private function groupInvoicesBySupplierAndDate(Collection $invoices): Collection
    {
        return $invoices->groupBy(function ($invoice) {
            return $invoice->supplier_id . '_' . $invoice->payment_date;
        })->map(function ($group) {
            $firstInvoice = $group->first();
            $totalAmount = $group->sum('total_amount');

            return [
                'supplier_id' => $firstInvoice->supplier_id,
                'supplier_name' => $firstInvoice->supplier->name,
                'payment_date' => $firstInvoice->payment_date,
                'currency' => $firstInvoice->currency,
                'total_amount' => $totalAmount,
                'invoice_count' => $group->count(),
                'is_overdue' => $this->isOverdue($firstInvoice->payment_date),
                'days_until_due' => $this->getDaysUntilDue($firstInvoice->payment_date),
                'invoices' => $group->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'control_number' => $invoice->control_number,
                        'total_amount' => $invoice->total_amount,
                        'currency' => $invoice->currency,
                        'exp_date' => $invoice->exp_date,
                        'status' => $invoice->status,
                    ];
                })
            ];
        })->values();
    }

    /**
     * Verificar si una fecha de pago está vencida
     */
    private function isOverdue(string $paymentDate): bool
    {
        return Carbon::parse($paymentDate)->isPast();
    }

    /**
     * Obtener días hasta el vencimiento (negativo si está vencido)
     */
    private function getDaysUntilDue(string $paymentDate): int
    {
        return Carbon::parse($paymentDate)->diffInDays(Carbon::now(), false);
    }

    /**
     * Procesar pago de facturas con conversión de moneda
     */
    public function processPayment(array $paymentData): array
    {
        $invoices = Invoice::whereIn('id', $paymentData['invoice_ids'])
            ->whereIn('status', ['pending', 'to_order'])
            ->get();

        if ($invoices->isEmpty()) {
            throw new \Exception('No se encontraron facturas válidas para procesar');
        }

        // Obtener tasa de cambio
        $exchangeRate = ExchangeRate::where('currency_code', $paymentData['payment_currency'])->first();
        if (!$exchangeRate) {
            throw new \Exception('No se encontró tasa de cambio para la moneda seleccionada');
        }

        // Calcular monto en USD
        $amountUSD = $paymentData['payment_amount'] / $exchangeRate->rate;

        // Crear registro de pago
        $paymentId = DB::table('invoice_payments')->insertGetId([
            'amount' => $paymentData['payment_amount'],
            'currency' => $paymentData['payment_currency'],
            'amount_usd' => $amountUSD,
            'exchange_rate' => $exchangeRate->rate,
            'payment_date' => $paymentData['payment_date'],
            'notes' => $paymentData['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualizar estado de las facturas
        Invoice::whereIn('id', $paymentData['invoice_ids'])->update([
            'status' => 'ordered',
            'payment_date' => $paymentData['payment_date'],
            'updated_at' => now(),
        ]);

        return [
            'payment_id' => $paymentId,
            'processed_invoices' => $paymentData['invoice_ids'],
            'amount_paid' => $paymentData['payment_amount'],
            'currency' => $paymentData['payment_currency'],
            'amount_usd' => $amountUSD,
            'exchange_rate' => $exchangeRate->rate
        ];
    }

    /**
     * Obtener estadísticas de pagos pendientes
     */
    public function getStatistics(): array
    {
        $totalPending = Invoice::whereIn('status', ['pending', 'to_order'])->count();
        $totalAmount = Invoice::whereIn('status', ['pending', 'to_order'])->sum('total_amount');

        $byCurrency = Invoice::whereIn('status', ['pending', 'to_order'])
            ->select('currency', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('currency')
            ->get();

        $bySupplier = Invoice::with('supplier')
            ->whereIn('status', ['pending', 'to_order'])
            ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('supplier_id')
            ->get();

        $overdueCount = Invoice::whereIn('status', ['pending', 'to_order'])
            ->whereNotNull('payment_date')
            ->whereDate('payment_date', '<', Carbon::now())
            ->count();

        return [
            'total_pending_invoices' => $totalPending,
            'total_amount_pending' => $totalAmount,
            'overdue_invoices' => $overdueCount,
            'by_currency' => $byCurrency,
            'by_supplier' => $bySupplier
        ];
    }

    /**
     * Obtener facturas próximas a vencer
     */
    public function getUpcomingDueInvoices(int $days = 7): Collection
    {
        $futureDate = Carbon::now()->addDays($days);

        return Invoice::with(['supplier'])
            ->whereIn('status', ['pending', 'to_order'])
            ->whereNotNull('payment_date')
            ->whereDate('payment_date', '<=', $futureDate)
            ->whereDate('payment_date', '>=', Carbon::now())
            ->orderBy('payment_date', 'asc')
            ->get();
    }

    /**
     * Obtener facturas vencidas
     */
    public function getOverdueInvoices(): Collection
    {
        return Invoice::with(['supplier'])
            ->whereIn('status', ['pending', 'to_order'])
            ->whereNotNull('payment_date')
            ->whereDate('payment_date', '<', Carbon::now())
            ->orderBy('payment_date', 'asc')
            ->get();
    }
}
