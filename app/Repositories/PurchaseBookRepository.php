<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\PurchaseBookContract;
use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use App\Models\GeneralSetting;
use App\Models\Invoice;
use App\Models\PurchaseBook;
use App\Models\PurchaseBookEntry;
use App\Models\Retention;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseBookRepository implements PurchaseBookContract
{
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseBook::with(['closedByUser:id,name'])
            ->withCount('entries');

        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('fortnight', 'desc');

        return $query->paginate($perPage);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): PurchaseBook
    {
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $periodType = $isSpecialTaxpayer ? PurchaseBookPeriodType::FORTNIGHTLY : PurchaseBookPeriodType::MONTHLY;
        $enableZoom = (bool) ($generalSetting?->enable_zoom ?? false);

        if ($periodType === PurchaseBookPeriodType::FORTNIGHTLY) {
            $fortnight = $fortnight ?? (now()->day <= 15 ? 1 : 2);
            if ($fortnight === 1) {
                $startDate = Carbon::create($year, $month, 1)->startOfDay();
                $endDate = Carbon::create($year, $month, 15)->endOfDay();
            } else {
                $startDate = Carbon::create($year, $month, 16)->startOfDay();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
            }
        } else {
            $fortnight = null;
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        }

        $query = PurchaseBook::where('year', $year)->where('month', $month);
        if ($fortnight !== null) {
            $query->where('fortnight', $fortnight);
        } else {
            $query->whereNull('fortnight');
        }

        $book = $query->first();

        if (!$book) {
            $book = PurchaseBook::create([
                'year' => $year,
                'month' => $month,
                'fortnight' => $fortnight,
                'period_type' => $periodType,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => PurchaseBookStatus::OPEN,
                'enable_zoom_snapshot' => $enableZoom,
            ]);
        }

        // Si el libro está abierto y no tiene entradas, sincronizar transacciones
        if ($book->isOpen() && $book->entries()->count() === 0) {
            try {
                $this->syncTransactions($book);
            } catch (\Throwable $e) {
                Log::warning("No se pudieron autosincronizar las transacciones del libro de compras ID {$book->id}: " . $e->getMessage());
            }
        }

        return $book;
    }

    public function getBookById(int $id): PurchaseBook
    {
        return PurchaseBook::with([
            'entries' => fn($q) => $q->orderBy('operation_number', 'asc'),
            'closedByUser:id,name',
        ])->findOrFail($id);
    }

    public function syncTransactions(PurchaseBook $book): PurchaseBook
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se pueden sincronizar transacciones en un libro cerrado.");
        }

        DB::transaction(function () use ($book) {
            $startDate = $book->start_date->format('Y-m-d');
            $endDate = $book->end_date->format('Y-m-d');

            // 1. Obtener Facturas válidas dentro del rango de fecha de emisión
            $invoices = Invoice::with(['supplier'])
                ->whereBetween('created_invoice_date', [$startDate, $endDate])
                ->orderBy('created_invoice_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // 2. Obtener Retenciones de IVA emitidas dentro del rango del libro
            $retentions = Retention::with(['supplier', 'invoices'])
                ->where(function ($q) {
                    $q->where('type', 'iva')
                      ->orWhere('type', 'IVA')
                      ->orWhereNull('type');
                })
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Guardar entradas manuales que el usuario haya modificado explícitamente
            $manualEntries = $book->entries()->where('is_manual_override', true)->get();

            // Eliminar entradas no protegidas para recrear la sincronización limpia
            $book->entries()->where('is_manual_override', false)->delete();

            $rawRows = collect();

            // Agregar Facturas como 01-REG
            foreach ($invoices as $inv) {
                // Verificar si esta factura de Zoom tiene alícuota reducida (ej. 8%)
                $isZoomSupplier = str_contains(strtoupper((string) $inv->supplier?->name), 'ZOOM') ||
                    str_contains(strtoupper((string) $inv->supplier?->social_reason), 'ZOOM');

                $taxableBase = (float) ($inv->taxable_base ?? 0);
                $taxAmount = (float) ($inv->tax_amount ?? 0);
                $exemptAmount = (float) ($inv->exempt_amount ?? 0);
                $totalAmount = (float) ($inv->total_amount ?? 0);

                // Si es Zoom y tiene tasa 8%, o si la alícuota calculada es 0.08
                $hasReduced = false;
                $reducedBase = 0;
                $reducedVat = 0;
                $generalBase = $taxableBase;
                $generalVat = $taxAmount;

                if ($taxableBase > 0 && round($taxAmount / $taxableBase, 2) === 0.08) {
                    $hasReduced = true;
                    $reducedBase = $taxableBase;
                    $reducedVat = $taxAmount;
                    $generalBase = 0;
                    $generalVat = 0;
                }

                $rawRows->push([
                    'type' => 'invoice',
                    'date' => $inv->created_invoice_date ? $inv->created_invoice_date->format('Y-m-d') : $startDate,
                    'invoice_id' => $inv->id,
                    'retention_id' => null,
                    'rif' => $inv->supplier?->rif ?? 'N/A',
                    'supplier_name' => $inv->supplier?->name ?: ($inv->supplier?->social_reason ?: 'PROVEEDOR VARIOS'),
                    'import_planilla_number' => null,
                    'import_dossier_number' => null,
                    'invoice_number' => (string) $inv->invoice_number,
                    'control_number' => (string) ($inv->control_number ?? ''),
                    'debit_note_number' => null,
                    'credit_note_number' => null,
                    'affected_invoice_number' => null,
                    'document_type' => 'FACTURA',
                    'transaction_type' => '01-REG',
                    'total_purchases_with_vat' => $totalAmount > 0 ? $totalAmount : round($exemptAmount + $taxableBase + $taxAmount, 2),
                    'exempt_purchases' => $exemptAmount,
                    'general_taxable_base' => $generalBase,
                    'general_aliquot' => $generalBase > 0 ? 0.16 : 0.00,
                    'general_vat' => $generalVat,
                    'reduced_taxable_base' => $reducedBase,
                    'reduced_aliquot' => $hasReduced ? 0.08 : 0.00,
                    'reduced_vat' => $reducedVat,
                    'vat_withheld' => 0.00,
                    'withholding_receipt_number' => null,
                ]);
            }

            // Agregar Comprobantes de Retención de IVA como 05-RET
            foreach ($retentions as $ret) {
                $affectedInvoices = $ret->invoices;
                $affectedInvoiceNum = $affectedInvoices->isNotEmpty()
                    ? $affectedInvoices->pluck('invoice_number')->implode(', ')
                    : null;

                $rawRows->push([
                    'type' => 'retention',
                    'date' => $ret->date ? $ret->date->format('Y-m-d') : $startDate,
                    'invoice_id' => $affectedInvoices->first()?->id,
                    'retention_id' => $ret->id,
                    'rif' => $ret->supplier?->rif ?? 'N/A',
                    'supplier_name' => $ret->supplier?->name ?: ($ret->supplier?->social_reason ?: 'PROVEEDOR VARIOS'),
                    'import_planilla_number' => null,
                    'import_dossier_number' => null,
                    'invoice_number' => null,
                    'control_number' => null,
                    'debit_note_number' => null,
                    'credit_note_number' => null,
                    'affected_invoice_number' => $affectedInvoiceNum,
                    'document_type' => 'RETENCIÓN',
                    'transaction_type' => '05-RET',
                    'total_purchases_with_vat' => 0.00,
                    'exempt_purchases' => 0.00,
                    'general_taxable_base' => (float) ($ret->total_taxable_base ?? 0),
                    'general_aliquot' => 0.16,
                    'general_vat' => (float) ($ret->total_tax_amount ?? 0),
                    'reduced_taxable_base' => 0.00,
                    'reduced_aliquot' => 0.00,
                    'reduced_vat' => 0.00,
                    'vat_withheld' => (float) ($ret->total_withheld_amount ?? 0),
                    'withholding_receipt_number' => (string) $ret->number,
                ]);
            }

            // Ordenar por fecha de operación cronológicamente
            $sorted = $rawRows->sortBy([
                ['date', 'asc'],
                ['invoice_number', 'asc'],
            ]);

            $opNum = 1;
            foreach ($sorted as $item) {
                PurchaseBookEntry::create([
                    'purchase_book_id' => $book->id,
                    'invoice_id' => $item['invoice_id'],
                    'retention_id' => $item['retention_id'],
                    'operation_number' => $opNum++,
                    'operation_date' => $item['date'],
                    'rif' => $item['rif'],
                    'supplier_name' => $item['supplier_name'],
                    'import_planilla_number' => $item['import_planilla_number'],
                    'import_dossier_number' => $item['import_dossier_number'],
                    'invoice_number' => $item['invoice_number'],
                    'control_number' => $item['control_number'],
                    'debit_note_number' => $item['debit_note_number'],
                    'credit_note_number' => $item['credit_note_number'],
                    'affected_invoice_number' => $item['affected_invoice_number'],
                    'document_type' => $item['document_type'],
                    'transaction_type' => $item['transaction_type'],
                    'total_purchases_with_vat' => $item['total_purchases_with_vat'],
                    'exempt_purchases' => $item['exempt_purchases'],
                    'general_taxable_base' => $item['general_taxable_base'],
                    'general_aliquot' => $item['general_aliquot'],
                    'general_vat' => $item['general_vat'],
                    'reduced_taxable_base' => $item['reduced_taxable_base'],
                    'reduced_aliquot' => $item['reduced_aliquot'],
                    'reduced_vat' => $item['reduced_vat'],
                    'vat_withheld' => $item['vat_withheld'],
                    'withholding_receipt_number' => $item['withholding_receipt_number'],
                    'is_manual_override' => false,
                ]);
            }

            // Reincorporar entradas manuales si existían
            foreach ($manualEntries as $mEntry) {
                $mEntry->update(['operation_number' => $opNum++]);
            }

            // Actualizar snapshot de enable_zoom según configuración actual
            $generalSetting = GeneralSetting::first();
            $book->update([
                'enable_zoom_snapshot' => (bool) ($generalSetting?->enable_zoom ?? false),
            ]);

            $this->recalculateTotals($book);
        });

        return $book->fresh(['entries']);
    }

    public function updateEntry(PurchaseBook $book, int $entryId, array $data): PurchaseBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede editar una entrada de un libro cerrado.");
        }

        $entry = $book->entries()->where('id', $entryId)->firstOrFail();

        $data['is_manual_override'] = true;
        $entry->update($data);

        $this->recalculateTotals($book);

        return $entry->fresh();
    }

    public function storeEntry(PurchaseBook $book, array $data): PurchaseBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede agregar entradas a un libro cerrado.");
        }

        $nextOpNumber = ($book->entries()->max('operation_number') ?? 0) + 1;
        $data['purchase_book_id'] = $book->id;
        $data['operation_number'] = $nextOpNumber;
        $data['is_manual_override'] = true;

        $entry = PurchaseBookEntry::create($data);
        $this->recalculateTotals($book);

        return $entry;
    }

    public function deleteEntry(PurchaseBook $book, int $entryId): bool
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede eliminar entradas de un libro cerrado.");
        }

        $entry = $book->entries()->where('id', $entryId)->firstOrFail();
        $deleted = $entry->delete();

        // Reordenar correlativos
        $entries = $book->entries()->orderBy('operation_number', 'asc')->get();
        $op = 1;
        foreach ($entries as $e) {
            $e->update(['operation_number' => $op++]);
        }

        $this->recalculateTotals($book);

        return $deleted;
    }

    public function closeBook(PurchaseBook $book, int $userId): PurchaseBook
    {
        if ($book->isClosed()) {
            throw new \DomainException("El libro ya se encuentra cerrado.");
        }

        $this->recalculateTotals($book);

        $generalSetting = GeneralSetting::first();
        $book->update([
            'status' => PurchaseBookStatus::CLOSED,
            'enable_zoom_snapshot' => (bool) ($generalSetting?->enable_zoom ?? false),
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);

        return $book->fresh();
    }

    public function recalculateTotals(PurchaseBook $book): void
    {
        $totals = $book->entries()
            ->selectRaw('
                COALESCE(SUM(total_purchases_with_vat), 0) as total_purchases_with_vat,
                COALESCE(SUM(exempt_purchases), 0) as total_exempt_purchases,
                COALESCE(SUM(general_taxable_base), 0) as total_general_taxable_base,
                COALESCE(SUM(general_vat), 0) as total_general_vat,
                COALESCE(SUM(reduced_taxable_base), 0) as total_reduced_taxable_base,
                COALESCE(SUM(reduced_vat), 0) as total_reduced_vat,
                COALESCE(SUM(vat_withheld), 0) as total_vat_withheld
            ')
            ->first();

        $book->update([
            'total_purchases_with_vat' => $totals->total_purchases_with_vat,
            'total_exempt_purchases' => $totals->total_exempt_purchases,
            'total_general_taxable_base' => $totals->total_general_taxable_base,
            'total_general_vat' => $totals->total_general_vat,
            'total_reduced_taxable_base' => $totals->total_reduced_taxable_base,
            'total_reduced_vat' => $totals->total_reduced_vat,
            'total_vat_withheld' => $totals->total_vat_withheld,
        ]);
    }
}
