<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\SalesBookContract;
use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use App\Models\FiscalCommand;
use App\Models\FiscalHistory;
use App\Models\FiscalZReport;
use App\Models\GeneralSetting;
use App\Models\SalesBook;
use App\Models\SalesBookEntry;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesBookRepository implements SalesBookContract
{
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesBook::with(['closedByUser:id,name'])
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

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): SalesBook
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

        $query = SalesBook::where('year', $year)->where('month', $month);
        if ($fortnight !== null) {
            $query->where('fortnight', $fortnight);
        } else {
            $query->whereNull('fortnight');
        }

        $book = $query->first();

        if (!$book) {
            $book = SalesBook::create([
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

        if ($book->isOpen() && $book->entries()->count() === 0) {
            try {
                $this->syncTransactions($book);
            } catch (\Throwable $e) {
                Log::warning("No se pudieron autosincronizar las transacciones del libro de ventas ID {$book->id}: " . $e->getMessage());
            }
        }

        return $book;
    }

    public function getBookById(int $id): SalesBook
    {
        return SalesBook::with([
            'entries' => fn($q) => $q->orderBy('operation_number', 'asc'),
            'closedByUser:id,name',
        ])->findOrFail($id);
    }

    public function syncTransactions(SalesBook $book): SalesBook
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se pueden sincronizar transacciones en un libro cerrado.");
        }

        DB::transaction(function () use ($book) {
            $startDate = $book->start_date->format('Y-m-d');
            $endDate = $book->end_date->format('Y-m-d');

            $generalSetting = GeneralSetting::first();
            $defaultSerial = $generalSetting?->fiscal_printer_serial;
            if (!$defaultSerial) {
                try {
                    $defaultSerial = FiscalHistory::whereNotNull('fiscal_id')->latest('id')->value('fiscal_id');
                } catch (\Throwable) {
                    $defaultSerial = null;
                }
            }
            if (!$defaultSerial) {
                $defaultSerial = 'Z1F0000379';
            }

            // 1. Obtener Reportes Z en el rango de fechas
            $zReports = FiscalZReport::whereBetween('report_date', [$startDate, $endDate])
                ->orderBy('report_date', 'asc')
                ->orderBy('report_number', 'asc')
                ->get();

            // 2. Obtener Notas de Crédito fiscales emitidas en el rango de fechas
            $creditNotes = FiscalCommand::where('command', 'CREDIT_NOTE')
                ->where('status', 'success')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->orderBy('created_at', 'asc')
                ->get();

            // Preservar entradas manuales (como comprobantes de retención de clientes u overrides)
            $manualEntries = $book->entries()->where('is_manual_override', true)->get();

            // Eliminar entradas no protegidas
            $book->entries()->where('is_manual_override', false)->delete();

            $rawRows = collect();

            // Agregar Reportes Z
            foreach ($zReports as $z) {
                $exempt = (float) $z->exempt_amount;
                $base16 = (float) $z->base_16_amount;
                $iva16 = (float) $z->iva_amount;
                $igtf = (float) ($z->igtf_amount > 0 ? $z->igtf_amount : ($z->igtf_base_amount ?? 0));
                $totalWithVat = (float) $z->total_amount;

                // Si total_amount viene en 0 o no cuadra, calcular suma de no gravadas + gravadas + iva
                if ($totalWithVat <= 0) {
                    $totalWithVat = round($exempt + $base16 + $iva16, 2);
                }

                $rawRows->push([
                    'type' => 'z_report',
                    'date' => $z->report_date ? $z->report_date->format('Y-m-d') : $startDate,
                    'fiscal_z_report_id' => $z->id,
                    'fiscal_command_id' => null,
                    'identification' => '0',
                    'client_name' => 'CLIENTES VARIOS',
                    'export_planilla_number' => null,
                    'document_type' => 'REPORTE Z',
                    'invoice_number' => null,
                    'control_number' => null,
                    'z_report_number' => (string) $z->report_number,
                    'z_first_invoice' => (string) ($z->first_invoice_number ?? ''),
                    'z_last_invoice' => (string) ($z->last_invoice_number ?? ''),
                    'machine_serial' => $defaultSerial,
                    'debit_note_number' => null,
                    'credit_note_number' => null,
                    'affected_invoice_number' => null,
                    'transaction_type' => '01-REG',
                    'total_sales_with_vat' => $totalWithVat,
                    'non_taxable_sales' => $exempt,
                    'taxpayer_taxable_base' => $base16,
                    'taxpayer_aliquot' => $base16 > 0 ? 0.16 : 0.00,
                    'taxpayer_vat' => $iva16,
                    'non_taxpayer_taxable_base' => 0.00,
                    'non_taxpayer_aliquot' => 0.00,
                    'non_taxpayer_vat' => 0.00,
                    'vat_withheld' => 0.00,
                    'withholding_receipt_number' => null,
                    'withholding_receipt_date' => null,
                    'igtf_amount' => $igtf,
                ]);
            }

            // Agregar Notas de Crédito (FiscalCommand CREDIT_NOTE)
            foreach ($creditNotes as $cn) {
                $payload = is_array($cn->payload)
                    ? $cn->payload
                    : (is_string($cn->payload) ? (json_decode($cn->payload, true) ?: []) : []);

                $date = $cn->created_at ? $cn->created_at->format('Y-m-d') : $startDate;

                $clientName = $payload['client_name'] ?? 'CLIENTE';
                $clientRif = $payload['client_rif'] ?? 'V000000000';
                $invoiceNumber = $payload['invoice_number'] ?? null;
                $machineSerial = $payload['machine_serial'] ?? $defaultSerial;
                $refundAmt = (float) ($payload['refund_amount'] ?? 0);
                $isTaxable = !empty($payload['is_taxable']);

                $baseAmt = 0.00;
                $ivaAmt = 0.00;
                $exemptAmt = 0.00;

                if ($isTaxable) {
                    $baseAmt = round($refundAmt / 1.16, 2);
                    $ivaAmt = round($refundAmt - $baseAmt, 2);
                } else {
                    $exemptAmt = $refundAmt;
                }

                // Obtener número de nota de crédito si vino en response
                $cnNum = null;
                if (is_array($cn->response) && !empty($cn->response['credit_note_number'])) {
                    $cnNum = (string) $cn->response['credit_note_number'];
                } elseif (is_string($cn->response) && preg_match('/(?:NC|Nota\s*Cr[eé]dito)[^\d]*(\d+)/i', $cn->response, $m)) {
                    $cnNum = (string) $m[1];
                } else {
                    $cnNum = (string) $cn->id;
                }

                $rawRows->push([
                    'type' => 'credit_note',
                    'date' => $date,
                    'fiscal_z_report_id' => null,
                    'fiscal_command_id' => $cn->id,
                    'identification' => $clientRif,
                    'client_name' => $clientName,
                    'export_planilla_number' => null,
                    'document_type' => 'NOTA DE CRÉDITO',
                    'invoice_number' => null,
                    'control_number' => null,
                    'z_report_number' => null,
                    'z_first_invoice' => null,
                    'z_last_invoice' => null,
                    'machine_serial' => $machineSerial,
                    'debit_note_number' => null,
                    'credit_note_number' => $cnNum,
                    'affected_invoice_number' => (string) $invoiceNumber,
                    'transaction_type' => '03-REG',
                    'total_sales_with_vat' => -$refundAmt,
                    'non_taxable_sales' => -$exemptAmt,
                    'taxpayer_taxable_base' => -$baseAmt,
                    'taxpayer_aliquot' => $isTaxable ? 0.16 : 0.00,
                    'taxpayer_vat' => -$ivaAmt,
                    'non_taxpayer_taxable_base' => 0.00,
                    'non_taxpayer_aliquot' => 0.00,
                    'non_taxpayer_vat' => 0.00,
                    'vat_withheld' => 0.00,
                    'withholding_receipt_number' => null,
                    'withholding_receipt_date' => null,
                    'igtf_amount' => 0.00,
                ]);
            }

            // Ordenar cronológicamente
            $sorted = $rawRows->sortBy([
                ['date', 'asc'],
                ['z_report_number', 'asc'],
            ]);

            $opNum = 1;
            foreach ($sorted as $item) {
                SalesBookEntry::create([
                    'sales_book_id' => $book->id,
                    'fiscal_z_report_id' => $item['fiscal_z_report_id'],
                    'fiscal_command_id' => $item['fiscal_command_id'],
                    'operation_number' => $opNum++,
                    'operation_date' => $item['date'],
                    'identification' => $item['identification'],
                    'client_name' => $item['client_name'],
                    'export_planilla_number' => $item['export_planilla_number'],
                    'document_type' => $item['document_type'],
                    'invoice_number' => $item['invoice_number'],
                    'control_number' => $item['control_number'],
                    'z_report_number' => $item['z_report_number'],
                    'z_first_invoice' => $item['z_first_invoice'],
                    'z_last_invoice' => $item['z_last_invoice'],
                    'machine_serial' => $item['machine_serial'],
                    'debit_note_number' => $item['debit_note_number'],
                    'credit_note_number' => $item['credit_note_number'],
                    'affected_invoice_number' => $item['affected_invoice_number'],
                    'transaction_type' => $item['transaction_type'],
                    'total_sales_with_vat' => $item['total_sales_with_vat'],
                    'non_taxable_sales' => $item['non_taxable_sales'],
                    'taxpayer_taxable_base' => $item['taxpayer_taxable_base'],
                    'taxpayer_aliquot' => $item['taxpayer_aliquot'],
                    'taxpayer_vat' => $item['taxpayer_vat'],
                    'non_taxpayer_taxable_base' => $item['non_taxpayer_taxable_base'],
                    'non_taxpayer_aliquot' => $item['non_taxpayer_aliquot'],
                    'non_taxpayer_vat' => $item['non_taxpayer_vat'],
                    'vat_withheld' => $item['vat_withheld'],
                    'withholding_receipt_number' => $item['withholding_receipt_number'],
                    'withholding_receipt_date' => $item['withholding_receipt_date'],
                    'igtf_amount' => $item['igtf_amount'],
                    'is_manual_override' => false,
                ]);
            }

            // Reincorporar entradas manuales
            foreach ($manualEntries as $mEntry) {
                $mEntry->update(['operation_number' => $opNum++]);
            }

            $book->update([
                'enable_zoom_snapshot' => (bool) ($generalSetting?->enable_zoom ?? false),
            ]);

            $this->recalculateTotals($book);
        });

        return $book->fresh(['entries']);
    }

    public function updateEntry(SalesBook $book, int $entryId, array $data): SalesBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede editar una entrada de un libro de ventas cerrado.");
        }

        $entry = $book->entries()->where('id', $entryId)->firstOrFail();
        $data['is_manual_override'] = true;
        $entry->update($data);

        $this->recalculateTotals($book);

        return $entry->fresh();
    }

    public function storeEntry(SalesBook $book, array $data): SalesBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede agregar entradas a un libro de ventas cerrado.");
        }

        $nextOpNumber = ($book->entries()->max('operation_number') ?? 0) + 1;
        $data['sales_book_id'] = $book->id;
        $data['operation_number'] = $nextOpNumber;
        $data['is_manual_override'] = true;

        $entry = SalesBookEntry::create($data);
        $this->recalculateTotals($book);

        return $entry;
    }

    public function deleteEntry(SalesBook $book, int $entryId): bool
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede eliminar entradas de un libro de ventas cerrado.");
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

    public function closeBook(SalesBook $book, int $userId): SalesBook
    {
        if ($book->isClosed()) {
            throw new \DomainException("El libro de ventas ya se encuentra cerrado.");
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

    public function recalculateTotals(SalesBook $book): void
    {
        $totals = $book->entries()
            ->selectRaw('
                COALESCE(SUM(total_sales_with_vat), 0) as total_sales_with_vat,
                COALESCE(SUM(non_taxable_sales), 0) as total_non_taxable_sales,
                COALESCE(SUM(taxpayer_taxable_base), 0) as total_taxpayer_taxable_base,
                COALESCE(SUM(taxpayer_vat), 0) as total_taxpayer_vat,
                COALESCE(SUM(non_taxpayer_taxable_base), 0) as total_non_taxpayer_taxable_base,
                COALESCE(SUM(non_taxpayer_vat), 0) as total_non_taxpayer_vat,
                COALESCE(SUM(vat_withheld), 0) as total_vat_withheld,
                COALESCE(SUM(igtf_amount), 0) as total_igtf
            ')
            ->first();

        $book->update([
            'total_sales_with_vat' => $totals->total_sales_with_vat,
            'total_non_taxable_sales' => $totals->total_non_taxable_sales,
            'total_taxpayer_taxable_base' => $totals->total_taxpayer_taxable_base,
            'total_taxpayer_vat' => $totals->total_taxpayer_vat,
            'total_non_taxpayer_taxable_base' => $totals->total_non_taxpayer_taxable_base,
            'total_non_taxpayer_vat' => $totals->total_non_taxpayer_vat,
            'total_vat_withheld' => $totals->total_vat_withheld,
            'total_igtf' => $totals->total_igtf,
        ]);
    }
}
