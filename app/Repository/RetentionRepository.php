<?php

namespace App\Repository;

use App\Models\Invoice;
use App\Models\Retention;
use Illuminate\Pagination\LengthAwarePaginator;

class RetentionRepository
{
    public function getInvoicesWithTax(array $filters, int $perPage): LengthAwarePaginator
    {
        $isGenerated = $filters['is_generated'] ?? false;
        $sortBy = $filters['sortBy'] ?? 'created_invoice_date';
        $orderBy = $filters['orderBy'] ?? 'desc';

        $query = Invoice::with('supplier')
            ->where('tax_amount', '>', 0)
            ->where('retention_generated', $isGenerated);

        if (empty($filters['start_date']) && empty($filters['end_date'])) {
            $query->whereYear('created_invoice_date', date('Y'));
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_invoice_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_invoice_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('control_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('social_reason', 'like', "%{$search}%")
                        ->orWhere('rif', 'like', "%{$search}%");
                  });
            });
        }

        // Handle sorting for nested properties like supplier.social_reason
        if (str_contains($sortBy, '.')) {
            $parts = explode('.', $sortBy);
            if ($parts[0] === 'supplier') {
                $query->join('suppliers', 'invoices.supplier_id', '=', 'suppliers.id')
                    ->orderBy("suppliers.{$parts[1]}", $orderBy)
                    ->select('invoices.*');
            }
        } else {
            $query->orderBy($sortBy, $orderBy);
        }

        return $query->paginate($perPage);
    }

    public function generateRetentions(array $invoiceIds): Retention
    {
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->where('retention_generated', false)
            ->get();

        if ($invoices->isEmpty()) {
            throw new \Exception("No hay facturas válidas para generar retención.");
        }

        $supplierId = $invoices->first()->supplier_id;

        if ($invoices->pluck('supplier_id')->unique()->count() > 1) {
            throw new \Exception("Todas las facturas deben ser del mismo proveedor.");
        }

        $retentionPercentage = 0.75;
        $totalTaxable = $invoices->sum('taxable_base');
        $totalTax = $invoices->sum('tax_amount');
        $totalWithheld = round($totalTax * $retentionPercentage, 2);

        // --- Nueva Lógica de Fecha Fiscal ---
        $fiscalDate = $this->calculateFiscalDate();
        $prefix = $fiscalDate->format('Ym');

        // --- Nueva Lógica de Numeración Continua ---
        // Buscamos la última retención absoluta en el sistema
        $lastOverall = Retention::orderBy('id', 'desc')->first();
        
        $nextCorrelative = 1;
        if ($lastOverall) {
            // Extraemos los últimos 8 dígitos del número (correlativo)
            // Asumiendo formato YYYYMMXXXXXXXX
            $lastCorrelative = substr($lastOverall->number, -8);
            $nextCorrelative = (int)$lastCorrelative + 1;
        }

        $number = $prefix . str_pad($nextCorrelative, 8, '0', STR_PAD_LEFT);

        $retention = Retention::create([
            'supplier_id' => $supplierId,
            'number' => $number,
            'date' => $fiscalDate,
            'total_taxable_base' => $totalTaxable,
            'total_tax_amount' => $totalTax,
            'total_withheld_amount' => $totalWithheld,
            'retention_percentage' => $retentionPercentage * 100,
        ]);

        Invoice::whereIn('id', $invoices->pluck('id'))
            ->update([
                'retention_generated' => true,
                'retention_id' => $retention->id
            ]);

        return $retention;
    }

    /**
     * Calcula la fecha fiscal según reglas de negocio:
     * 14-17 -> Día 15
     * 30-31 -> Fin de mes actual
     * 1-2   -> Fin de mes anterior
     * Otros -> Fecha del día
     */
    private function calculateFiscalDate(): \Carbon\Carbon
    {
        $now = now();
        $day = $now->day;

        if ($day >= 14 && $day <= 17) {
            return $now->copy()->day(15);
        }

        if ($day >= 30) {
            return $now->copy()->endOfMonth();
        }

        if ($day >= 1 && $day <= 2) {
            return $now->copy()->subMonth()->endOfMonth();
        }

        return $now;
    }

    /**
     * Genera todas las retenciones pendientes para todos los proveedores en un rango.
     */
    public function generateAllPendingInRange(string $startDate, string $endDate): int
    {
        $pendingInvoices = Invoice::where('tax_amount', '>', 0)
            ->where('retention_generated', false)
            ->whereDate('created_invoice_date', '>=', $startDate)
            ->whereDate('created_invoice_date', '<=', $endDate)
            ->get();

        if ($pendingInvoices->isEmpty()) {
            return 0;
        }

        $groupedBySupplier = $pendingInvoices->groupBy('supplier_id');
        $generatedCount = 0;

        foreach ($groupedBySupplier as $supplierId => $invoices) {
            $this->generateRetentions($invoices->pluck('id')->toArray());
            $generatedCount++;
        }

        return $generatedCount;
    }

    public function getGeneratedRetentions(array $filters, int $perPage): LengthAwarePaginator
    {
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderBy = $filters['orderBy'] ?? 'desc';

        $query = Retention::with(['supplier', 'invoices']);

        if (!empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('social_reason', 'like', "%{$search}%")
                        ->orWhere('rif', 'like', "%{$search}%");
                  });
            });
        }

        // Handle sorting for nested properties
        if (str_contains($sortBy, '.')) {
            $parts = explode('.', $sortBy);
            if ($parts[0] === 'supplier') {
                $query->join('suppliers', 'retentions.supplier_id', '=', 'suppliers.id')
                    ->orderBy("suppliers.{$parts[1]}", $orderBy)
                    ->select('retentions.*');
            }
        } else {
            $query->orderBy($sortBy, $orderBy);
        }

        return $query->paginate($perPage);
    }
}
