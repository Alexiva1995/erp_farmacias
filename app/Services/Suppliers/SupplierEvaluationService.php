<?php

namespace App\Services\Suppliers;

class SupplierEvaluationService
{
    /**
     * Evalúa masivamente a todos los proveedores del sistema.
     */
    public function evaluateAll(): void
    {
        $suppliers = Supplier::all();

        // Calcular gastos totales para el indicador de volumen
        $totalSystemSpend = \App\Models\Invoice::sum('total_amount_discount');
        if ($totalSystemSpend <= 0) {
            $totalSystemSpend = \App\Models\Invoice::sum('total_amount');
        }
        $totalSystemSpend = max($totalSystemSpend, 1); // Evitar división por cero

        foreach ($suppliers as $supplier) {
            $this->evaluate($supplier, $totalSystemSpend);
        }
    }

    /**
     * Evalúa un proveedor específico y guarda su puntaje en supplier_scores.
     */
    public function evaluate(Supplier $supplier, float $totalSystemSpend = null): SupplierScore
    {
        if ($totalSystemSpend === null) {
            $totalSystemSpend = \App\Models\Invoice::sum('total_amount_discount') ?: \App\Models\Invoice::sum('total_amount');
            $totalSystemSpend = max($totalSystemSpend, 1);
        }

        $breakdown = [
            'product_arrival' => $this->calculateProductArrival($supplier),
            'returns_ratio' => $this->calculateReturnsRatio($supplier),
            'volume' => $this->calculateVolume($supplier, $totalSystemSpend),
            'frequency' => $this->calculateFrequency($supplier),
            'consistency' => $this->calculateConsistency($supplier)
        ];

        // Sumar todos los indicadores (máximo 100)
        $totalScore = array_sum($breakdown);

        // Guardar o actualizar el score actual
        $scoreModel = $supplier->scores()->create([
            'score' => $totalScore,
            'breakdown' => $breakdown,
            'evaluated_on' => now()->toDateString()
        ]);

        return $scoreModel;
    }

    /**
     * 1. Tasa de Llegada de Productos (30 pts)
     * Porcentaje de órdenes de compra (auto_order_details) completadas.
     */
    private function calculateProductArrival(Supplier $supplier): float
    {
        // Órdenes de compra del proveedor
        $orders = \App\Models\AutoOrder::where('supplier_id', $supplier->id)->get();
        if ($orders->isEmpty()) return 0; // Sin órdenes, no gana estos puntos

        $totalDetails = 0;
        $completedDetails = 0;

        foreach ($orders as $order) {
            $details = $order->details;
            $totalDetails += $details->count();
            // Consideramos completado si status == 1 (completado)
            $completedDetails += $details->where('status', 1)->count();
        }

        if ($totalDetails === 0) return 0;

        return ($completedDetails / $totalDetails) * 30;
    }

    /**
     * 2. Ratio de Devoluciones (25 pts)
     * Qué porcentaje del monto facturado se ha devuelto.
     */
    private function calculateReturnsRatio(Supplier $supplier): float
    {
        $invoices = $supplier->invoices;
        if ($invoices->isEmpty()) return 12.5; // Punto medio si no hay facturas (neutral)

        $totalInvoiced = $invoices->sum('total_amount');
        if ($totalInvoiced <= 0) return 0;

        $totalReturned = \App\Models\InvoiceReturn::whereIn('invoice_id', $invoices->pluck('id'))->sum('amount_refunded');

        $returnRatio = min($totalReturned / $totalInvoiced, 1);
        
        // Mientras menos devoluciones, mayor puntaje (25 pts max)
        return (1 - $returnRatio) * 25;
    }

    /**
     * 3. Volumen de Compras (20 pts)
     * Peso relativo de este proveedor en el sistema.
     */
    private function calculateVolume(Supplier $supplier, float $totalSystemSpend): float
    {
        $supplierSpend = $supplier->invoices()->sum('total_amount_discount') ?: $supplier->invoices()->sum('total_amount');
        
        $volumeRatio = min($supplierSpend / $totalSystemSpend, 1);

        // Escalamos un poco el volumen (para no penalizar tan fuerte a proveedores medianos)
        // Usamos raíz cuadrada para curva logarítmica
        return sqrt($volumeRatio) * 20;
    }

    /**
     * 4. Frecuencia de Facturación (15 pts)
     * Confianza por historial de transacciones. Max 100 facturas.
     */
    private function calculateFrequency(Supplier $supplier): float
    {
        $totalInvoices = $supplier->invoices()->count();
        $frequencyRatio = min($totalInvoices / 100, 1);
        return $frequencyRatio * 15;
    }

    /**
     * 5. Consistencia de Unidades (10 pts)
     * De los detalles de órdenes, ¿cuántas unidades se recibieron vs las pedidas?
     */
    private function calculateConsistency(Supplier $supplier): float
    {
        $orders = \App\Models\AutoOrder::where('supplier_id', $supplier->id)->get();
        if ($orders->isEmpty()) return 0;

        $totalRequested = 0;
        $totalReceived = 0;

        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $totalRequested += $detail->quantity;
                // Asumiendo que facturadas son las recibidas (o si hay status 1)
                if ($detail->status == 1) {
                    $totalReceived += $detail->quantity;
                }
            }
        }

        if ($totalRequested === 0) return 0;

        $consistencyRatio = min($totalReceived / $totalRequested, 1);
        return $consistencyRatio * 10;
    }
}
