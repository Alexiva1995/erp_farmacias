<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\Retention;
use Illuminate\Pagination\LengthAwarePaginator;

class RetentionRepository implements \App\Contracts\Retention
{
    public function getInvoicesWithTax(array $filters, int $perPage): LengthAwarePaginator
    {
        $isGenerated = $filters['is_generated'] ?? false;
        $sortBy = $filters['sortBy'] ?? 'created_invoice_date';
        $orderBy = $filters['orderBy'] ?? 'desc';

        // Validar columnas para evitar SQL error 500
        $validInvoiceColumns = ['id', 'invoice_number', 'control_number', 'total_amount', 'taxable_base', 'tax_amount', 'created_invoice_date'];
        if (!str_contains($sortBy, '.') && !in_array($sortBy, $validInvoiceColumns)) {
            $sortBy = 'created_invoice_date';
        }

        $retentionType = $filters['retention_type'] ?? 'iva';

        $query = Invoice::with(['supplier:id,name,social_reason,rif,address'])
            ->select([
                'invoices.id', 'invoices.supplier_id', 'invoices.invoice_number', 'invoices.control_number',
                'invoices.exempt_amount', 'invoices.taxable_base', 'invoices.tax_amount', 'invoices.total_amount',
                'invoices.created_invoice_date', 'invoices.retention_generated', 'invoices.retention_id',
                'invoices.is_expense', 'invoices.has_islr_retention', 'invoices.islr_retention_generated', 'invoices.islr_retention_id'
            ]);

        if ($retentionType === 'islr') {
            $query->where('invoices.has_islr_retention', true)
                  ->where('invoices.islr_retention_generated', $isGenerated);
        } else {
            $query->where('invoices.tax_amount', '>', 0)
                  ->where('invoices.retention_generated', $isGenerated);
        }

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
            $query->orderBy("invoices.{$sortBy}", $orderBy);
        }

        return $query->paginate($perPage);
    }

    /**
     * Genera comprobantes de retención para las facturas dadas (máximo 8 facturas por comprobante).
     *
     * @param array $invoiceIds
     * @param string|null $retentionDate
     * @return \Illuminate\Support\Collection<int, Retention>
     */
    public function generateRetentions(array $invoiceIds, ?string $retentionDate = null): \Illuminate\Support\Collection
    {
        $invoices = Invoice::with('supplier')
            ->whereIn('id', $invoiceIds)
            ->where('retention_generated', false)
            ->get();

        if ($invoices->isEmpty()) {
            throw new \Exception("No hay facturas válidas para generar retención.");
        }

        $supplier = $invoices->first()->supplier;
        $supplierId = $supplier?->id ?? $invoices->first()->supplier_id;

        if ($invoices->pluck('supplier_id')->unique()->count() > 1) {
            throw new \Exception("Todas las facturas deben ser del mismo proveedor.");
        }

        // Validación fiscal: el proveedor debe tener RIF y Dirección Fiscal registrados
        $hasRif = $supplier && !empty(trim((string) $supplier->rif));
        $hasAddress = $supplier && !empty(trim((string) $supplier->address));

        if (!$hasRif || !$hasAddress) {
            $supplierName = $supplier->name ?? $supplier->social_reason ?? "ID: {$supplierId}";
            $missing = [];
            if (!$hasRif) $missing[] = 'RIF';
            if (!$hasAddress) $missing[] = 'Dirección Fiscal';
            $missingStr = implode(' y ', $missing);

            throw new \Exception("No se puede generar retención para '{$supplierName}' porque no tiene {$missingStr} registrado.");
        }

        $retentionPercentage = 0.75;

        // --- Fecha Fiscal de Emisión (Personalizada o Calculada) ---
        $fiscalDate = $retentionDate ? \Carbon\Carbon::parse($retentionDate) : $this->calculateFiscalDate();
        $prefix = $fiscalDate->format('Ym');

        // --- Numeración Continua (Garantizando Unicidad) ---
        $lastOverall = Retention::orderBy('id', 'desc')->first();
        
        $nextCorrelative = 1;
        if ($lastOverall && strlen($lastOverall->number) >= 8) {
            $lastCorrelative = substr($lastOverall->number, -8);
            $nextCorrelative = (int)$lastCorrelative + 1;
        }

        // Límite legal / fiscal: máximo 7 facturas por comprobante de retención
        $chunks = $invoices->chunk(7);
        $createdRetentions = collect();

        foreach ($chunks as $chunk) {
            $totalTaxable = $chunk->sum('taxable_base');
            $totalTax = $chunk->sum('tax_amount');
            $totalWithheld = round($totalTax * $retentionPercentage, 2);

            do {
                $number = $prefix . str_pad((string)$nextCorrelative, 8, '0', STR_PAD_LEFT);
                $nextCorrelative++;
            } while (Retention::where('number', $number)->exists());

            $retention = Retention::create([
                'supplier_id' => $supplierId,
                'number' => $number,
                'date' => $fiscalDate,
                'total_taxable_base' => $totalTaxable,
                'total_tax_amount' => $totalTax,
                'total_withheld_amount' => $totalWithheld,
                'retention_percentage' => $retentionPercentage * 100,
            ]);

            Invoice::whereIn('id', $chunk->pluck('id'))
                ->update([
                    'retention_generated' => true,
                    'retention_id' => $retention->id
                ]);

            $createdRetentions->push($retention);
        }

        return $createdRetentions;
    }

    /**
     * Calcula la fecha fiscal según reglas de negocio:
     * 14-17 -> Día 15
     * 30-31 -> Fin de mes actual
     * 1-2   -> Fin de mes anterior
     * Otros -> Fecha del día
     */
    private function calculateFiscalDate(?string $endDate = null): \Carbon\Carbon
    {
        if ($endDate) {
            return \Carbon\Carbon::parse($endDate);
        }

        $now = now();
        
        if ($now->day <= 15) {
            return $now->copy()->day(15);
        }

        return $now->copy()->endOfMonth();
    }

    /**
     * Genera todas las retenciones pendientes para todos los proveedores en un rango.
     */
    public function generateAllPendingInRange(string $startDate, string $endDate, ?string $retentionDate = null): int
    {
        $pendingInvoices = Invoice::with('supplier')
            ->where('tax_amount', '>', 0)
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
            $supplier = $invoices->first()->supplier;
            $hasRif = $supplier && !empty(trim((string) $supplier->rif));
            $hasAddress = $supplier && !empty(trim((string) $supplier->address));

            // Si el proveedor no tiene RIF o dirección fiscal, no se le genera retención
            if (!$hasRif || !$hasAddress) {
                continue;
            }

            $created = $this->generateRetentions($invoices->pluck('id')->toArray(), $retentionDate);
            $generatedCount += count($created);
        }

        return $generatedCount;
    }

    public function getGeneratedRetentions(array $filters, int $perPage): LengthAwarePaginator
    {
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderBy = $filters['orderBy'] ?? 'desc';

        if ($sortBy === 'created_invoice_date') {
            $sortBy = 'date';
        }

        // Validar columnas para evitar SQL error 500
        $validRetentionColumns = ['id', 'number', 'date', 'total_taxable_base', 'total_tax_amount', 'total_withheld_amount'];
        if (!str_contains($sortBy, '.') && !in_array($sortBy, $validRetentionColumns)) {
            $sortBy = 'date';
        }

        $retentionType = $filters['retention_type'] ?? 'iva';

        $query = Retention::with([
            'supplier:id,name,social_reason,rif',
        ])
        ->select([
            'retentions.id', 'retentions.type', 'retentions.supplier_id', 'retentions.number',
            'retentions.date', 'retentions.total_taxable_base', 'retentions.total_tax_amount',
            'retentions.total_withheld_amount', 'retentions.retention_percentage',
            'retentions.concept_description', 'retentions.subtrahend'
        ])
        ->where('retentions.type', $retentionType);

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
            $query->orderBy("retentions.{$sortBy}", $orderBy);
        }

        return $query->paginate($perPage);
    }

    public function deleteRetention(int $id): bool
    {
        $retention = Retention::findOrFail($id);

        if (($retention->type?->value ?? $retention->type) === 'islr') {
            Invoice::where('islr_retention_id', $retention->id)
                ->update([
                    'islr_retention_generated' => false,
                    'islr_retention_id' => null
                ]);
        } else {
            Invoice::where('retention_id', $retention->id)
                ->update([
                    'retention_generated' => false,
                    'retention_id' => null
                ]);
        }

        return $retention->delete();
    }

    public function prepareRetentionData($source): array
    {
        if ($source instanceof Retention) {
            $retention = $source->load(['supplier', 'invoices']);
            $invoices = $retention->invoices;
            $supplier = $retention->supplier;
            $comprobanteNumber = $retention->number;
            $dateNow = $retention->date->format('d/m/Y');
            $period = $retention->date->format('Ym');
        } else {
            if (is_array($source)) {
                $invoices = Invoice::with('supplier')->whereIn('id', $source)->get();
            } else {
                $invoices = collect([$source]);
            }

            if ($invoices->isEmpty()) {
                throw new \Exception("Sin facturas.");
            }

            $firstInvoice = $invoices->first();
            $supplier = $firstInvoice->supplier;
            $comprobanteNumber = $invoices->pluck('id')->implode('-');
            $dateNow = now()->format('d/m/Y');
            $period = optional($firstInvoice->created_invoice_date)->format('Ym') ?? now()->format('Ym');
        }

        $retentionPercentage = 0.75;
        $totalInvoiceAmount = 0;
        $totalExempt = 0;
        $totalTaxable = 0;
        $totalTax = 0;
        $totalWithheld = 0;

        $invoiceLines = $invoices->map(function ($inv) use ($retentionPercentage, &$totalInvoiceAmount, &$totalExempt, &$totalTaxable, &$totalTax, &$totalWithheld) {
            $taxWithheld = round(($inv->tax_amount ?? 0) * $retentionPercentage, 2);
            $totalInvoiceAmount += (float)$inv->total_amount;
            $totalExempt += (float)$inv->exempt_amount;
            $totalTaxable += (float)$inv->taxable_base;
            $totalTax += (float)$inv->tax_amount;
            $totalWithheld += $taxWithheld;

            return [
                'date' => optional($inv->created_invoice_date)->format('d/m/Y'),
                'number' => $inv->invoice_number,
                'control' => $inv->control_number,
                'total' => (float)$inv->total_amount,
                'exempt_amount' => (float)$inv->exempt_amount,
                'taxable_base' => (float)$inv->taxable_base,
                'tax_amount' => (float)$inv->tax_amount,
                'tax_withheld' => $taxWithheld,
            ];
        });

        return [
            'date_now' => $dateNow,
            'comprobante' => [
                'number' => $comprobanteNumber,
                'period' => $period,
            ],
            'company' => [
                'name' => 'FARMACIA BARRIO SUCRE 2024, C.A.',
                'rif' => 'J505406957',
                'address' => 'CALLE PRINCIPAL LOCAL 05 (L3) SECTOR BARRIO SUCRE LA FRIA TACHIRA',
            ],
            'supplier' => [
                'name' => $supplier->social_reason ?? $supplier->name ?? 'Proveedor Desconocido',
                'rif' => $supplier->rif ?? 'J-00000000-0',
                'address' => $supplier->address ?? 'N/A',
            ],
            'invoices' => $invoiceLines,
            'totals' => [
                'total_amount' => $totalInvoiceAmount,
                'exempt_amount' => $totalExempt,
                'taxable_base' => $totalTaxable,
                'tax_amount' => $totalTax,
                'tax_withheld' => $totalWithheld,
                'retention_percentage' => ($retentionPercentage * 100) . '%',
            ]
        ];
    }

    public function updateRetention(int $id, array $data): Retention
    {
        $retention = Retention::findOrFail($id);
        $retention->update($data);
        return $retention;
    }

    /**
     * Obtiene una retención con sus facturas asociadas cargadas (eager load).
     */
    public function getRetentionWithInvoices(int $id): Retention
    {
        return Retention::with(['supplier:id,name,social_reason,rif', 'invoices'])->findOrFail($id);
    }

    /**
     * Actualiza los datos del comprobante y los campos editables de las facturas vinculadas.
     * Desvincula facturas removidas y recalcula los totales automáticamente.
     */
    public function updateRetentionWithInvoices(int $id, array $data, array $invoices): Retention
    {
        $retention = Retention::findOrFail($id);

        if (!empty($data)) {
            $retention->update($data);
        }

        $submittedIds = array_filter(array_column($invoices, 'id'));

        // Desvincular las facturas que fueron quitadas de este comprobante
        Invoice::where('retention_id', $id)
            ->whereNotIn('id', $submittedIds)
            ->update([
                'retention_id' => null,
                'retention_generated' => false,
            ]);

        // Actualizar facturas conservadas
        foreach ($invoices as $invoiceData) {
            if (!empty($invoiceData['id'])) {
                Invoice::where('id', $invoiceData['id'])
                    ->where('retention_id', $id)
                    ->update([
                        'control_number' => $invoiceData['control_number'] ?? null,
                        'invoice_number' => $invoiceData['invoice_number'] ?? null,
                    ]);
            }
        }

        // Recalcular totales del comprobante con las facturas vinculadas actuales
        $remainingInvoices = Invoice::where('retention_id', $id)->get();
        $retentionPercentage = ($retention->retention_percentage ?? 75) / 100;
        $totalTaxable = $remainingInvoices->sum('taxable_base');
        $totalTax = $remainingInvoices->sum('tax_amount');
        $totalWithheld = round($totalTax * $retentionPercentage, 2);

        $retention->update([
            'total_taxable_base' => $totalTaxable,
            'total_tax_amount' => $totalTax,
            'total_withheld_amount' => $totalWithheld,
        ]);

        return $retention->fresh();
    }

    /**
     * Omite todas las facturas pendientes con fecha igual o anterior a $cutoffDate
     */
    public function omitInvoicesUntilDate(string $cutoffDate): int
    {
        return Invoice::where('tax_amount', '>', 0)
            ->where('retention_generated', false)
            ->whereDate('created_invoice_date', '<=', $cutoffDate)
            ->update([
                'retention_generated' => true,
            ]);
    }

    /**
     * Restaura todas las facturas omitidas (retention_generated = true pero sin comprobante asignado).
     */
    public function restoreOmittedInvoices(): int
    {
        return Invoice::where('tax_amount', '>', 0)
            ->where('retention_generated', true)
            ->whereNull('retention_id')
            ->update([
                'retention_generated' => false,
            ]);
    }

    public function getNextIslrCorrelative(): array
    {
        $lastIslr = Retention::where('type', 'islr')->orderBy('id', 'desc')->first();

        if (!$lastIslr) {
            return [
                'is_first' => true,
                'next_number' => null,
            ];
        }

        $cleanNum = preg_replace('/\D/', '', $lastIslr->number);
        $nextSeq = ((int) $cleanNum) + 1;
        $formatted = str_pad((string) $nextSeq, 10, '0', STR_PAD_LEFT);

        return [
            'is_first' => false,
            'next_number' => $formatted,
            'last_number' => $lastIslr->number,
        ];
    }

    public function generateIslrRetention(array $data): Retention
    {
        $invoiceIds = $data['invoice_ids'];
        $invoices = Invoice::with('supplier')
            ->whereIn('id', $invoiceIds)
            ->where('has_islr_retention', true)
            ->where('islr_retention_generated', false)
            ->get();

        if ($invoices->isEmpty()) {
            throw new \Exception('No hay facturas válidas para generar retención de ISLR.');
        }

        if ($invoices->pluck('supplier_id')->unique()->count() > 1) {
            throw new \Exception('Todas las facturas de la retención deben pertenecer al mismo proveedor.');
        }

        $supplier = $invoices->first()->supplier;
        $supplierId = $supplier?->id ?? $invoices->first()->supplier_id;

        // Determinar número de comprobante
        $correlativeInfo = $this->getNextIslrCorrelative();
        if ($correlativeInfo['is_first']) {
            if (empty($data['starting_number'])) {
                throw new \Exception('Por ser la primera retención de ISLR, debe indicar el número de comprobante inicial.');
            }
            $cleanStart = preg_replace('/\D/', '', (string) $data['starting_number']);
            $number = str_pad($cleanStart ?: '1', 10, '0', STR_PAD_LEFT);
        } else {
            $number = !empty($data['starting_number'])
                ? str_pad(preg_replace('/\D/', '', (string) $data['starting_number']), 10, '0', STR_PAD_LEFT)
                : $correlativeInfo['next_number'];
        }

        while (Retention::where('type', 'islr')->where('number', $number)->exists()) {
            $numVal = ((int) preg_replace('/\D/', '', $number)) + 1;
            $number = str_pad((string) $numVal, 10, '0', STR_PAD_LEFT);
        }

        $date = !empty($data['date']) ? \Carbon\Carbon::parse($data['date']) : now();
        $percentage = (float) ($data['retention_percentage'] ?? 2.0);
        $subtrahend = (float) ($data['subtrahend'] ?? 0.0);
        $concept = trim((string) ($data['concept_description'] ?? 'SERVICIOS'));

        $totalTaxable = $invoices->sum('taxable_base');
        $totalTaxAmount = $invoices->sum('tax_amount');
        $withheld = round(($totalTaxable * ($percentage / 100)) - $subtrahend, 2);
        if ($withheld < 0) {
            $withheld = 0.00;
        }

        return \DB::transaction(function () use (
            $supplierId, $number, $date, $totalTaxable, $totalTaxAmount,
            $withheld, $percentage, $concept, $subtrahend, $invoices
        ) {
            $retention = Retention::create([
                'supplier_id' => $supplierId,
                'type' => \App\Enums\RetentionType::ISLR,
                'number' => $number,
                'date' => $date,
                'total_taxable_base' => $totalTaxable,
                'total_tax_amount' => $totalTaxAmount,
                'total_withheld_amount' => $withheld,
                'retention_percentage' => $percentage,
                'concept_description' => $concept,
                'subtrahend' => $subtrahend,
            ]);

            Invoice::whereIn('id', $invoices->pluck('id'))->update([
                'islr_retention_generated' => true,
                'islr_retention_id' => $retention->id,
            ]);

            return $retention;
        });
    }

    public function prepareIslrRetentionData($source): array
    {
        if ($source instanceof Retention) {
            $retention = $source->load(['supplier', 'islrInvoices']);
            $invoices = $retention->islrInvoices;
            $supplier = $retention->supplier;
            $comprobanteNumber = $retention->number;
            $emissionDate = $retention->date ? $retention->date->format('d/m/Y') : now()->format('d/m/Y');
            $year = $retention->date ? $retention->date->format('Y') : now()->format('Y');
            $month = $retention->date ? $retention->date->format('m') : now()->format('m');
            $percentage = (float) $retention->retention_percentage;
            $subtrahend = (float) $retention->subtrahend;
            $concept = $retention->concept_description ?: 'SERVICIOS';
        } else {
            $invoiceIds = is_array($source) ? $source : [$source];
            $invoices = Invoice::with('supplier')->whereIn('id', $invoiceIds)->get();
            if ($invoices->isEmpty()) {
                throw new \Exception('No se encontraron facturas para la retención ISLR.');
            }
            $supplier = $invoices->first()->supplier;
            $comprobanteNumber = '0000000001';
            $emissionDate = now()->format('d/m/Y');
            $year = now()->format('Y');
            $month = now()->format('m');
            $percentage = 2.00;
            $subtrahend = 0.00;
            $concept = 'SERVICIOS';
        }

        $generalSetting = \App\Models\GeneralSetting::first();

        $operNo = 1;
        $operations = $invoices->map(function ($inv) use (&$operNo, $concept, $percentage, $subtrahend) {
            $base = (float) $inv->taxable_base;
            $withheld = round(($base * ($percentage / 100)) - $subtrahend, 2);
            if ($withheld < 0) $withheld = 0.00;

            $invNumber = $inv->invoice_number;
            if (!str_starts_with(strtoupper($invNumber), 'F-') && !str_starts_with(strtoupper($invNumber), 'FAC-')) {
                $docNumber = 'F-' . $invNumber;
            } else {
                $docNumber = $invNumber;
            }

            return [
                'oper_no' => $operNo++,
                'doc_date' => optional($inv->created_invoice_date)->format('d/m/Y') ?? '-',
                'doc_number' => $docNumber,
                'control_number' => $inv->control_number ?: '-',
                'concept_description' => $concept,
                'taxable_base' => $base,
                'subtrahend' => $subtrahend,
                'retention_percentage' => $percentage,
                'withheld_amount' => $withheld,
            ];
        });

        return [
            'comprobante_number' => $comprobanteNumber,
            'emission_date' => $emissionDate,
            'period_year' => $year,
            'period_month' => $month,
            'agent' => [
                'name' => $generalSetting?->app_name ?: 'ENSALUD FARMACIA EL TAMA C.A.',
                'rif' => $generalSetting?->app_rif ?: 'J407570170',
                'address' => $generalSetting?->address ?: 'AV 4 EDIFICIO MINICENTRO PARAMILLO 1 ETAPA LOCAL NRO 3 ZONA INDUSTRIAL DE PARAMILLO SAN CRISTOBAL TACHIRA ZONA POSTAL 5001',
            ],
            'subject' => [
                'name' => $supplier?->social_reason ?: $supplier?->name ?: 'PROVEEDOR',
                'rif' => $supplier?->rif ?: 'J000000000',
                'address' => $supplier?->address ?: 'DIRECCIÓN FISCAL',
            ],
            'operations' => $operations,
            'totals' => [
                'taxable_base' => $operations->sum('taxable_base'),
                'subtrahend' => $operations->sum('subtrahend'),
                'withheld_amount' => $operations->sum('withheld_amount'),
            ],
        ];
    }
}
