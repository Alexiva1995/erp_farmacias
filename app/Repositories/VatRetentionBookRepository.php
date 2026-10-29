<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\VatRetentionBookContract;
use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use App\Models\GeneralSetting;
use App\Models\Retention;
use App\Models\VatRetentionBook;
use App\Models\VatRetentionBookEntry;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VatRetentionBookRepository implements VatRetentionBookContract
{
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = VatRetentionBook::with(['closedByUser:id,name'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('fortnight', 'desc');

        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): VatRetentionBook
    {
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $periodType = $isSpecialTaxpayer
            ? PurchaseBookPeriodType::Fortnightly
            : PurchaseBookPeriodType::Monthly;

        if ($periodType === PurchaseBookPeriodType::Fortnightly && $fortnight === null) {
            $now = Carbon::now();
            $fortnight = ($now->year === $year && $now->month === $month)
                ? ($now->day <= 15 ? 1 : 2)
                : 1;
        }

        if ($periodType === PurchaseBookPeriodType::Fortnightly) {
            $startDate = Carbon::create($year, $month, $fortnight === 1 ? 1 : 16)->startOfDay();
            $endDate = ($fortnight === 1)
                ? Carbon::create($year, $month, 15)->endOfDay()
                : Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        } else {
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
            $fortnight = null;
        }

        $book = VatRetentionBook::firstOrCreate(
            [
                'year' => $year,
                'month' => $month,
                'fortnight' => $fortnight,
            ],
            [
                'period_type' => $periodType,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => PurchaseBookStatus::Open,
            ]
        );

        if ($book->wasRecentlyCreated || $book->isOpen()) {
            $this->syncTransactions($book);
        }

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function getBookById(int $id): VatRetentionBook
    {
        return VatRetentionBook::with([
            'entries' => fn($query) => $query->orderBy('operation_number', 'asc'),
            'closedByUser:id,name',
        ])->findOrFail($id);
    }

    public function syncTransactions(VatRetentionBook $book): VatRetentionBook
    {
        if ($book->isClosed()) {
            return $book;
        }

        $startDateStr = Carbon::parse($book->start_date)->startOfDay()->toDateString();
        $endDateStr = Carbon::parse($book->end_date)->endOfDay()->toDateString();

        $settings = GeneralSetting::first() ?? new GeneralSetting();
        $rawRif = $settings->app_rif ?: $settings->rif ?: 'J-00000000-0';
        $agentRif = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $rawRif));
        $fiscalPeriod = sprintf('%04d%02d', $book->year, $book->month);

        // Consultar retenciones de IVA en el rango de fechas de emisión del comprobante
        $retentions = Retention::with(['supplier', 'invoices.supplier'])
            ->where(function ($q) {
                $q->where('type', 'iva')
                  ->orWhereNull('type')
                  ->orWhere('type', '');
            })
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->orderBy('number', 'asc')
            ->orderBy('date', 'asc')
            ->get();

        DB::transaction(function () use ($book, $retentions, $agentRif, $fiscalPeriod) {
            // Limpiar entradas previas para recalcular
            $book->entries()->delete();

            $operationNumber = 1;
            $totalDocs = 0;
            $totalDocAmount = 0.0;
            $totalTaxable = 0.0;
            $totalTax = 0.0;
            $totalWithheld = 0.0;
            $totalExempt = 0.0;

            foreach ($retentions as $retention) {
                $supplier = $retention->supplier;
                $supplierRif = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($supplier?->rif ?? 'J000000000')));
                $supplierName = $supplier?->social_reason ?: $supplier?->name ?: 'PROVEEDOR GENERAL';

                $retentionPercentage = ((float) $retention->retention_percentage) > 0
                    ? ((float) $retention->retention_percentage) / 100
                    : 0.75;

                $invoices = $retention->invoices;

                if ($invoices->isEmpty()) {
                    // Si por alguna razón la retención no tiene facturas hijas asociadas directamente
                    $docAmount = (float) $retention->total_taxable_base + (float) $retention->total_tax_amount;
                    $taxable = (float) $retention->total_taxable_base;
                    $tax = (float) $retention->total_tax_amount;
                    $withheld = (float) $retention->total_withheld_amount;

                    VatRetentionBookEntry::create([
                        'vat_retention_book_id' => $book->id,
                        'retention_id' => $retention->id,
                        'invoice_id' => null,
                        'operation_number' => $operationNumber++,
                        'agent_rif' => $agentRif,
                        'fiscal_period' => $fiscalPeriod,
                        'document_date' => $retention->date ? $retention->date->toDateString() : now()->toDateString(),
                        'operation_type' => 'C',
                        'document_type' => '01',
                        'supplier_rif' => $supplierRif,
                        'supplier_name' => $supplierName,
                        'document_number' => $retention->number,
                        'control_number' => '00-000000',
                        'total_document_amount' => $docAmount,
                        'taxable_base' => $taxable,
                        'tax_amount' => $tax,
                        'withheld_amount' => $withheld,
                        'affected_document_number' => '0',
                        'retention_number' => $retention->number,
                        'exempt_amount' => 0.0,
                        'tax_rate' => 16.00,
                        'import_dossier_number' => '0',
                    ]);

                    $totalDocs++;
                    $totalDocAmount += $docAmount;
                    $totalTaxable += $taxable;
                    $totalTax += $tax;
                    $totalWithheld += $withheld;
                    continue;
                }

                foreach ($invoices as $inv) {
                    $invSupplier = $inv->supplier ?: $supplier;
                    $invSupplierRif = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($invSupplier?->rif ?: $supplierRif)));
                    $invSupplierName = $invSupplier?->social_reason ?: $invSupplier?->name ?: $supplierName;

                    $invTaxAmount = (float) ($inv->tax_amount ?? 0);
                    $invWithheld = round($invTaxAmount * $retentionPercentage, 2);
                    $invTaxable = (float) ($inv->taxable_base ?? 0);
                    $invExempt = (float) ($inv->exempt_amount ?? 0);
                    $invTotal = (float) ($inv->total_amount ?? ($invTaxable + $invTaxAmount + $invExempt));

                    // Determinar tipo de documento
                    $docType = '01';
                    $affectedDoc = '0';
                    $invoiceNum = (string) $inv->invoice_number;

                    if (stripos($invoiceNum, 'NC') !== false || stripos($invoiceNum, 'NOTA DE C') !== false) {
                        $docType = '03';
                        $affectedDoc = (string) ($inv->affected_invoice_number ?: '0');
                    } elseif (stripos($invoiceNum, 'ND') !== false || stripos($invoiceNum, 'NOTA DE D') !== false) {
                        $docType = '02';
                        $affectedDoc = (string) ($inv->affected_invoice_number ?: '0');
                    }

                    // Determinar alícuota
                    $taxRate = 16.00;
                    if ($invTaxable > 0 && $invTaxAmount > 0) {
                        $calculatedRate = round(($invTaxAmount / $invTaxable) * 100, 1);
                        if ($calculatedRate > 0) {
                            $taxRate = $calculatedRate;
                        }
                    }

                    VatRetentionBookEntry::create([
                        'vat_retention_book_id' => $book->id,
                        'retention_id' => $retention->id,
                        'invoice_id' => $inv->id,
                        'operation_number' => $operationNumber++,
                        'agent_rif' => $agentRif,
                        'fiscal_period' => $fiscalPeriod,
                        'document_date' => optional($inv->created_invoice_date)->toDateString() ?: ($retention->date ? $retention->date->toDateString() : now()->toDateString()),
                        'operation_type' => 'C',
                        'document_type' => $docType,
                        'supplier_rif' => $invSupplierRif,
                        'supplier_name' => $invSupplierName,
                        'document_number' => $inv->invoice_number ?: '000000',
                        'control_number' => $inv->control_number ?: '',
                        'total_document_amount' => $invTotal,
                        'taxable_base' => $invTaxable,
                        'tax_amount' => $invTaxAmount,
                        'withheld_amount' => $invWithheld,
                        'affected_document_number' => $affectedDoc,
                        'retention_number' => $retention->number,
                        'exempt_amount' => $invExempt,
                        'tax_rate' => $taxRate,
                        'import_dossier_number' => '0',
                    ]);

                    $totalDocs++;
                    $totalDocAmount += $invTotal;
                    $totalTaxable += $invTaxable;
                    $totalTax += $invTaxAmount;
                    $totalWithheld += $invWithheld;
                    $totalExempt += $invExempt;
                }
            }

            $book->update([
                'total_documents' => $totalDocs,
                'total_document_amount' => $totalDocAmount,
                'total_taxable_base' => $totalTaxable,
                'total_tax_amount' => $totalTax,
                'total_withheld_amount' => $totalWithheld,
                'total_exempt_amount' => $totalExempt,
            ]);
        });

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function updateEntry(VatRetentionBook $book, int $entryId, array $data): VatRetentionBookEntry
    {
        $entry = $book->entries()->findOrFail($entryId);

        $entry->update([
            'document_number' => $data['document_number'] ?? $entry->document_number,
            'control_number' => $data['control_number'] ?? $entry->control_number,
            'document_type' => $data['document_type'] ?? $entry->document_type,
            'affected_document_number' => $data['affected_document_number'] ?? $entry->affected_document_number,
            'total_document_amount' => isset($data['total_document_amount']) ? (float) $data['total_document_amount'] : $entry->total_document_amount,
            'taxable_base' => isset($data['taxable_base']) ? (float) $data['taxable_base'] : $entry->taxable_base,
            'tax_amount' => isset($data['tax_amount']) ? (float) $data['tax_amount'] : $entry->tax_amount,
            'withheld_amount' => isset($data['withheld_amount']) ? (float) $data['withheld_amount'] : $entry->withheld_amount,
            'exempt_amount' => isset($data['exempt_amount']) ? (float) $data['exempt_amount'] : $entry->exempt_amount,
            'tax_rate' => isset($data['tax_rate']) ? (float) $data['tax_rate'] : $entry->tax_rate,
        ]);

        // Recalcular totales consolidados del libro
        $book->update([
            'total_documents' => $book->entries()->count(),
            'total_document_amount' => $book->entries()->sum('total_document_amount'),
            'total_taxable_base' => $book->entries()->sum('taxable_base'),
            'total_tax_amount' => $book->entries()->sum('tax_amount'),
            'total_withheld_amount' => $book->entries()->sum('withheld_amount'),
            'total_exempt_amount' => $book->entries()->sum('exempt_amount'),
        ]);

        return $entry;
    }

    public function closeBook(VatRetentionBook $book, int $userId): VatRetentionBook
    {
        $book->update([
            'status' => PurchaseBookStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function reopenBook(VatRetentionBook $book): VatRetentionBook
    {
        $book->update([
            'status' => PurchaseBookStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $this->syncTransactions($book);
    }
}
