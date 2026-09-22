<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\AutoOrder;
use App\Models\Invoice;
use App\Models\InvoiceReturn;
use App\Models\Supplier;
use App\Models\SupplierScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierEvaluationService
{
    /**
     * Evalúa masivamente a todos los proveedores del sistema considerando los últimos 90 días.
     */
    public function evaluateAll(): void
    {
        $since = Carbon::now()->subDays(90)->startOfDay();
        $suppliers = Supplier::all();

        foreach ($suppliers as $supplier) {
            $this->evaluate($supplier, $since);
        }
    }

    /**
     * Evalúa un proveedor específico y guarda su puntaje en supplier_scores.
     */
    public function evaluate(Supplier $supplier, ?Carbon $since = null): SupplierScore
    {
        $since = $since ?? Carbon::now()->subDays(90)->startOfDay();

        $fillRateData = $this->calculateFillRate($supplier, $since);
        $onTimeData = $this->calculateOnTime($supplier, $since);
        $qualityData = $this->calculateQuality($supplier, $since);
        $adminAccuracyData = $this->calculateAdminAccuracy($supplier, $since);
        $commercialData = $this->calculateCommercialConditions($supplier);

        $hasOrders = $fillRateData['score'] !== null;
        $hasInvoices = $qualityData['score'] !== null;

        $breakdown = [
            'fill_rate' => [
                'score' => $fillRateData['score'],
                'max'   => 30,
                'label' => $hasOrders ? 'Completez (Fill Rate)' : 'Completez (N/A - Sin OC)',
            ],
            'on_time' => [
                'score' => $onTimeData['score'],
                'max'   => 20,
                'label' => $hasOrders ? 'A Tiempo (On-Time)' : 'A Tiempo (N/A - Sin OC)',
            ],
            'quality' => [
                'score' => $qualityData['score'] ?? 0.0,
                'max'   => 25,
                'label' => 'Calidad y Devoluciones',
            ],
            'admin_accuracy' => [
                'score' => $adminAccuracyData['score'] ?? 0.0,
                'max'   => 15,
                'label' => 'Precisión Administrativa',
            ],
            'commercial_conditions' => [
                'score' => $commercialData['score'],
                'max'   => 10,
                'label' => 'Condiciones Comerciales',
            ],
            'is_rescaled' => !$hasOrders && $hasInvoices,
            'evaluated_at' => now()->toDateString(),
        ];

        // Cálculo de Score Total
        if ($hasOrders) {
            // Evaluación completa de 5 criterios (Base 100)
            $totalScore = ($fillRateData['score'] ?? 0.0)
                + ($onTimeData['score'] ?? 0.0)
                + ($qualityData['score'] ?? 0.0)
                + ($adminAccuracyData['score'] ?? 0.0)
                + ($commercialData['score'] ?? 0.0);
        } elseif ($hasInvoices) {
            // Reescalado Proporcional sobre criterios evaluables (Base 50 -> 100)
            $evaluableEarned = ($qualityData['score'] ?? 0.0)
                + ($adminAccuracyData['score'] ?? 0.0)
                + ($commercialData['score'] ?? 0.0);

            $totalScore = ($evaluableEarned / 50.0) * 100.0;
        } else {
            // Proveedor nuevo o sin actividad reciente en los 90 días
            $totalScore = ($commercialData['score'] / 10.0) * 50.0; // Ponderación inicial por condiciones
        }

        $totalScore = round(min(100.0, max(0.0, $totalScore)), 1);

        return $supplier->scores()->create([
            'score'        => $totalScore,
            'breakdown'    => $breakdown,
            'evaluated_on' => now()->toDateString(),
        ]);
    }

    /**
     * 1. Completez / Fill Rate (Máx. 30 pts)
     * Unidades recibidas / Unidades pedidas en OC de los últimos 90 días.
     */
    private function calculateFillRate(Supplier $supplier, Carbon $since): array
    {
        $orders = AutoOrder::where('supplier_id', $supplier->id)
            ->where('created_at', '>=', $since)
            ->with('details')
            ->get();

        if ($orders->isEmpty()) {
            return ['score' => null];
        }

        $totalRequested = 0;
        $totalReceived = 0;

        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $totalRequested += (float) $detail->quantity;
                if ($detail->received == 1 || $detail->status == 1) {
                    $totalReceived += (float) $detail->quantity;
                }
            }
        }

        if ($totalRequested <= 0) {
            return ['score' => null];
        }

        $ratio = min(1.0, max(0.0, $totalReceived / $totalRequested));
        return ['score' => round($ratio * 30.0, 1)];
    }

    /**
     * 2. A Tiempo / On-Time (Máx. 20 pts)
     * Entregas realizadas en o antes de la fecha tentativa prometida en los últimos 90 días.
     */
    private function calculateOnTime(Supplier $supplier, Carbon $since): array
    {
        $orders = AutoOrder::where('supplier_id', $supplier->id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', [1, 2])
            ->get();

        if ($orders->isEmpty()) {
            return ['score' => null];
        }

        $totalEvaluated = 0;
        $onTimeCount = 0;

        foreach ($orders as $order) {
            $totalEvaluated++;
            $tentative = $order->tentative_delivery_date ? Carbon::parse($order->tentative_delivery_date)->endOfDay() : null;

            // Fecha real de recepción a través de facturas asociadas o fecha de cierre
            $actualDeliveryDate = Invoice::where('auto_order_id', $order->id)
                ->value('received_date');

            if ($actualDeliveryDate) {
                $actual = Carbon::parse($actualDeliveryDate)->startOfDay();
                if (!$tentative || $actual->lte($tentative)) {
                    $onTimeCount++;
                }
            } elseif ($order->status->value === 2 || (int)$order->status === 2) {
                $actual = Carbon::parse($order->updated_at);
                if (!$tentative || $actual->lte($tentative->addDay())) {
                    $onTimeCount++;
                }
            } else {
                // Si aún está en tránsito dentro de fecha
                if ($tentative && Carbon::now()->lte($tentative)) {
                    $onTimeCount++;
                }
            }
        }

        if ($totalEvaluated <= 0) {
            return ['score' => null];
        }

        $ratio = min(1.0, max(0.0, $onTimeCount / $totalEvaluated));
        return ['score' => round($ratio * 20.0, 1)];
    }

    /**
     * 3. Calidad y Cero Devoluciones (Máx. 25 pts)
     * 1 - (Monto devuelto / Monto facturado) * 25 en los últimos 90 días.
     */
    private function calculateQuality(Supplier $supplier, Carbon $since): array
    {
        $invoices = $supplier->invoices()
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'deleted')
            ->get();

        if ($invoices->isEmpty()) {
            return ['score' => null];
        }

        $totalInvoiced = (float) $invoices->sum('total_amount');
        if ($totalInvoiced <= 0) {
            return ['score' => 25.0];
        }

        $invoiceIds = $invoices->pluck('id');
        $totalReturned = (float) InvoiceReturn::whereIn('invoice_id', $invoiceIds)->sum('amount_refunded');

        if ($totalReturned <= 0) {
            return ['score' => 25.0];
        }

        $returnRatio = min(1.0, $totalReturned / $totalInvoiced);
        return ['score' => round((1.0 - $returnRatio) * 25.0, 1)];
    }

    /**
     * 4. Precisión Administrativa (Máx. 15 pts)
     * Porcentaje de facturas sin discrepancias de precio, reclamos o notas de débito referenciales.
     */
    private function calculateAdminAccuracy(Supplier $supplier, Carbon $since): array
    {
        $invoices = $supplier->invoices()
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'deleted')
            ->get();

        if ($invoices->isEmpty()) {
            return ['score' => null];
        }

        $totalInvoices = $invoices->count();
        $cleanInvoices = $invoices->filter(function ($inv) {
            $hasDiscrepancy = ((float) ($inv->nd_referential_amount ?? 0)) > 0;
            $hasClaim = ((float) ($inv->claim_amount ?? 0)) > 0;
            return !$hasDiscrepancy && !$hasClaim;
        })->count();

        $ratio = min(1.0, max(0.0, $cleanInvoices / $totalInvoices));
        return ['score' => round($ratio * 15.0, 1)];
    }

    /**
     * 5. Condiciones Comerciales y Competitividad (Máx. 10 pts)
     * Días de crédito, facilidades de pago y convenios comerciales.
     */
    private function calculateCommercialConditions(Supplier $supplier): array
    {
        $score = 0.0;

        // Días de crédito (hasta 5 pts: 30+ días = 5 pts, 15 días = 2.5 pts)
        $creditDays = (int) ($supplier->credit_days ?? 0);
        $creditScore = min(5.0, ($creditDays / 30.0) * 5.0);
        $score += $creditScore;

        // Convenios, pronto pago, descuentos comerciales o no cobro IGTF (hasta 5 pts)
        $hasDiscounts = $supplier->discounts()->exists();
        $hasPaymentRules = $supplier->paymentRules()->exists();
        $noIgtf = !$supplier->charges_igtf;

        $commercialBonus = 0.0;
        if ($hasDiscounts || $hasPaymentRules) {
            $commercialBonus += 3.0;
        }
        if ($noIgtf) {
            $commercialBonus += 2.0;
        }

        $score += min(5.0, $commercialBonus);

        return ['score' => round(min(10.0, max(0.0, $score)), 1)];
    }
}
