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

        $query = Invoice::with('supplier')
            ->where('tax_amount', '>', 0)
            ->where('retention_generated', $isGenerated)
            ->orderBy('created_invoice_date', 'desc');

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
                  ->orWhere('control_number', 'like', "%{$search}%");
            });
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

        $retention = Retention::create([
            'supplier_id' => $supplierId,
            'number' => 'TEMP-' . uniqid(),
            'date' => now(),
            'total_taxable_base' => $totalTaxable,
            'total_tax_amount' => $totalTax,
            'total_withheld_amount' => $totalWithheld,
            'retention_percentage' => $retentionPercentage * 100,
        ]);

        $retention->update(['number' => 'RET-' . str_pad($retention->id, 6, '0', STR_PAD_LEFT)]);

        Invoice::whereIn('id', $invoices->pluck('id'))
            ->update([
                'retention_generated' => true,
                'retention_id' => $retention->id
            ]);

        return $retention;
    }

    public function getGeneratedRetentions(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Retention::with(['supplier', 'invoices'])
            ->orderBy('date', 'desc');

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
                      $sq->where('social_reason', 'like', "%{$search}%")
                        ->orWhere('rif', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate($perPage);
    }
}
