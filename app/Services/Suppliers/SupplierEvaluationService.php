<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReturn;
use App\Models\ProductSupplier;
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
        $priceVariationData = $this->calculatePriceVariation($supplier, $since);
        $commercialData = $this->calculateCommercialConditions($supplier);

        $fillScore = $fillRateData['score'] ?? 0.0;
        $onTimeScore = $onTimeData['score'] ?? 0.0;
        $qualityScore = $qualityData['score'] ?? 0.0;
        $priceScore = $priceVariationData['score'] ?? 0.0;
        $commercialScore = $commercialData['score'] ?? 0.0;

        $breakdown = [
            'fill_rate' => [
                'score' => $fillScore,
                'max'   => 30,
                'label' => 'Completez (Fill Rate)',
            ],
            'on_time' => [
                'score' => $onTimeScore,
                'max'   => 20,
                'label' => 'A Tiempo (On-Time)',
            ],
            'quality' => [
                'score' => $qualityScore,
                'max'   => 25,
                'label' => 'Calidad y Devoluciones',
            ],
            'price_variation' => [
                'score'                => $priceScore,
                'max'                  => 15,
                'label'                => 'Variación de Precio (OC vs Factura)',
                'evaluated_items'      => $priceVariationData['evaluated_items'] ?? 0,
                'exact_count'          => $priceVariationData['exact_count'] ?? 0,
                'cheaper_count'        => $priceVariationData['cheaper_count'] ?? 0,
                'more_expensive_count' => $priceVariationData['more_expensive_count'] ?? 0,
                'avg_variation_pct'    => $priceVariationData['avg_variation_pct'] ?? 0.0,
            ],
            'commercial_conditions' => [
                'score' => $commercialScore,
                'max'   => 10,
                'label' => 'Condiciones Comerciales',
            ],
            'evaluated_at' => now()->toDateString(),
        ];

        // Evaluación unificada de los 5 criterios sobre base 100
        $totalScore = $fillScore
            + $onTimeScore
            + $qualityScore
            + $priceScore
            + $commercialScore;

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

            $statusVal = $order->status instanceof \App\Enums\AutoOrderStatus ? $order->status->value : (int) $order->status;
            if ($actualDeliveryDate) {
                $actual = Carbon::parse($actualDeliveryDate)->startOfDay();
                if (!$tentative || $actual->lte($tentative)) {
                    $onTimeCount++;
                }
            } elseif ($statusVal === \App\Enums\AutoOrderStatus::COMPLETED->value) {
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
     * 4. Variación de Precio (Máx. 15 pts)
     * Compara el costo unitario USD facturado contra el solicitado en AutoOrden (con respaldo en catálogo).
     * Mismo costo o menor (tolerancia +0.5%): puntaje completo. Sobrecosto: penalización proporcional.
     */
    private function calculatePriceVariation(Supplier $supplier, Carbon $since): array
    {
        $invoices = $supplier->invoices()
            ->select(['id', 'auto_order_id', 'currency', 'exchange_rate'])
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'deleted')
            ->get();

        if ($invoices->isEmpty()) {
            return [
                'score' => null,
                'evaluated_items' => 0,
                'exact_count' => 0,
                'cheaper_count' => 0,
                'more_expensive_count' => 0,
                'avg_variation_pct' => 0.0,
            ];
        }

        $invoiceMap = $invoices->keyBy('id');
        $lines = InvoiceDetail::whereIn('invoice_id', $invoiceMap->keys())
            ->select([
                'id',
                'invoice_id',
                'product_id',
                'auto_order_id',
                'auto_order_details_id',
                'unit_cost',
                'supplier_discount_percentage',
            ])
            ->get();

        if ($lines->isEmpty()) {
            return [
                'score' => null,
                'evaluated_items' => 0,
                'exact_count' => 0,
                'cheaper_count' => 0,
                'more_expensive_count' => 0,
                'avg_variation_pct' => 0.0,
            ];
        }

        // Pre-cargar referencias sin N+1
        $detailIds = $lines->pluck('auto_order_details_id')->filter()->unique()->values()->all();
        $orderIds = $lines->pluck('auto_order_id')
            ->merge($invoices->pluck('auto_order_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productIds = $lines->pluck('product_id')->filter()->unique()->values()->all();

        $orderDetailsById = !empty($detailIds)
            ? AutoOrderDetail::whereIn('id', $detailIds)
                ->get(['id', 'order_id', 'product_id', 'unit_cost'])
                ->keyBy('id')
            : collect();

        $orderDetailsByOrderProduct = collect();
        if (!empty($orderIds)) {
            $orderDetailsByOrderProduct = AutoOrderDetail::whereIn('order_id', $orderIds)
                ->get(['id', 'order_id', 'product_id', 'unit_cost'])
                ->keyBy(fn ($d) => $d->order_id . ':' . $d->product_id);
        }

        $catalogCostByProduct = !empty($productIds)
            ? ProductSupplier::where('supplier_id', $supplier->id)
                ->whereIn('product_id', $productIds)
                ->pluck('unit_cost_usd', 'product_id')
            : collect();

        $evaluatedItems = 0;
        $exactCount = 0;
        $cheaperCount = 0;
        $moreExpensiveCount = 0;
        $totalPoints = 0.0;
        $sumVariationPct = 0.0;

        foreach ($lines as $line) {
            $invoice = $invoiceMap->get($line->invoice_id);
            if (!$invoice) {
                continue;
            }

            $rawUnitCost = (float) ($line->unit_cost ?? 0);
            if ($rawUnitCost <= 0) {
                continue;
            }

            // Resolver costo unitario USD de referencia en AutoOrden (prioridad directa, luego orden+producto, luego catálogo)
            $effOrderId = $line->auto_order_id ?: $invoice->auto_order_id;
            $refCostUsd = 0.0;

            if (!empty($line->auto_order_details_id) && ($aoDetail = $orderDetailsById->get($line->auto_order_details_id))) {
                $refCostUsd = (float) ($aoDetail->unit_cost ?? 0);
            }
            if ($refCostUsd <= 0 && !empty($effOrderId) && !empty($line->product_id)) {
                $aoDetail = $orderDetailsByOrderProduct->get($effOrderId . ':' . $line->product_id);
                if ($aoDetail) {
                    $refCostUsd = (float) ($aoDetail->unit_cost ?? 0);
                }
            }
            if ($refCostUsd <= 0 && !empty($line->product_id)) {
                $refCostUsd = (float) ($catalogCostByProduct->get($line->product_id) ?? 0);
            }

            if ($refCostUsd <= 0) {
                continue;
            }

            // Normalizar costo facturado a USD aplicando tasa de cambio y descuento comercial si existe
            $currencyUpper = strtoupper(trim((string) ($invoice->currency ?? 'USD')));
            $exchangeRate = (float) ($invoice->exchange_rate ?? 0);
            $isUsd = in_array($currencyUpper, ['USD', '$', 'DOLAR', 'DOLARES', ''], true);

            $invoicedCostUsd = (!$isUsd && $exchangeRate > 0)
                ? ($rawUnitCost / $exchangeRate)
                : $rawUnitCost;

            $discountPct = (float) ($line->supplier_discount_percentage ?? 0);
            if ($discountPct > 0) {
                $invoicedCostUsd *= (1.0 - min(100.0, $discountPct) / 100.0);
            }

            $variationPct = (($invoicedCostUsd - $refCostUsd) / $refCostUsd) * 100.0;
            $sumVariationPct += $variationPct;
            $evaluatedItems++;

            if ($variationPct <= -0.5) {
                // Facturado más económico que la orden de compra
                $cheaperCount++;
                $totalPoints += 1.0;
            } elseif ($variationPct <= 0.5) {
                // Precio respetado dentro de tolerancia de redondeo
                $exactCount++;
                $totalPoints += 1.0;
            } else {
                // Sobreprecio respecto a lo solicitado (20% o más lleva a 0 esta línea)
                $moreExpensiveCount++;
                $totalPoints += max(0.0, 1.0 - ($variationPct / 20.0));
            }
        }

        if ($evaluatedItems === 0) {
            return [
                'score' => null,
                'evaluated_items' => 0,
                'exact_count' => 0,
                'cheaper_count' => 0,
                'more_expensive_count' => 0,
                'avg_variation_pct' => 0.0,
            ];
        }

        $ratio = min(1.0, max(0.0, $totalPoints / $evaluatedItems));

        return [
            'score'                => round($ratio * 15.0, 1),
            'evaluated_items'      => $evaluatedItems,
            'exact_count'          => $exactCount,
            'cheaper_count'        => $cheaperCount,
            'more_expensive_count' => $moreExpensiveCount,
            'avg_variation_pct'    => round($sumVariationPct / $evaluatedItems, 2),
        ];
    }

    /**
     * 5. Condiciones Comerciales y Competitividad (Máx. 10 pts)
     * Días de crédito, facilidades de pago y convenios comerciales (sin penalización por IGTF).
     */
    private function calculateCommercialConditions(Supplier $supplier): array
    {
        $score = 0.0;

        // Días de crédito (hasta 5 pts: 30+ días = 5 pts, 15 días = 2.5 pts)
        $creditDays = (int) ($supplier->credit_days ?? 0);
        $creditScore = min(5.0, ($creditDays / 30.0) * 5.0);
        $score += $creditScore;

        // Convenios: pronto pago, descuentos generales o escalas por laboratorio (hasta 5 pts)
        $hasPaymentRules = $supplier->paymentRules()->exists();
        $hasDiscounts = $supplier->discounts()->exists()
            || $supplier->laboratoryLinks()->whereHas('discountRules')->exists();

        $commercialBonus = 0.0;
        if ($hasPaymentRules) {
            $commercialBonus += 2.5;
        }
        if ($hasDiscounts) {
            $commercialBonus += 2.5;
        }

        $score += min(5.0, $commercialBonus);

        return ['score' => round(min(10.0, max(0.0, $score)), 1)];
    }
}
