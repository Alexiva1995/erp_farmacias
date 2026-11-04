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
use App\Models\PurchaseBookExcludedInvoice;
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
        $book = PurchaseBook::findOrFail($id);
        if ($book->isOpen()) {
            $this->reorderOperations($book);
        }

        return PurchaseBook::with([
            'entries' => fn($q) => $q->whereRaw('UPPER(supplier_name) NOT LIKE ?', ['%INFORMAL%'])
                ->with(['invoice.supplier', 'retention.supplier', 'retention.invoices'])
                ->orderBy('operation_number', 'asc'),
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

            // 1. Obtener Facturas válidas dentro del rango de fecha de emisión (excluyendo proveedores informales)
            $invoices = Invoice::with(['supplier'])
                ->whereDoesntHave('supplier', function ($q) {
                    $q->whereRaw('UPPER(name) LIKE ?', ['%INFORMAL%'])
                      ->orWhereRaw('UPPER(social_reason) LIKE ?', ['%INFORMAL%']);
                })
                ->whereBetween('created_invoice_date', [$startDate, $endDate])
                ->orderBy('created_invoice_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // 2. Obtener Retenciones de IVA emitidas dentro del rango del libro (excluyendo proveedores informales)
            $retentions = Retention::with(['supplier', 'invoices'])
                ->whereDoesntHave('supplier', function ($q) {
                    $q->whereRaw('UPPER(name) LIKE ?', ['%INFORMAL%'])
                      ->orWhereRaw('UPPER(social_reason) LIKE ?', ['%INFORMAL%']);
                })
                ->where(function ($q) {
                    $q->where('type', 'iva')
                      ->orWhere('type', 'IVA')
                      ->orWhereNull('type');
                })
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Obtener registros de facturas o retenciones expresamente excluidas de este libro
            $excludedRecords = PurchaseBookExcludedInvoice::where('purchase_book_id', $book->id)->get();
            $excludedInvoiceIds = $excludedRecords->pluck('invoice_id')->filter()->all();
            $excludedInvoiceNumbers = $excludedRecords->pluck('invoice_number')->filter()->all();
            $excludedRetentionIds = $excludedRecords->pluck('retention_id')->filter()->all();

            // Guardar entradas manuales que el usuario haya modificado explícitamente (excluyendo proveedores informales)
            $manualEntries = $book->entries()
                ->where('is_manual_override', true)
                ->whereRaw('UPPER(supplier_name) NOT LIKE ?', ['%INFORMAL%'])
                ->get();

            // Guardar IDs de facturas y retenciones que ya están preservadas por entradas manuales
            $manualInvoiceIds = $manualEntries->pluck('invoice_id')->filter()->all();
            $manualRetentionIds = $manualEntries->pluck('retention_id')->filter()->all();

            // Eliminar entradas no protegidas o con proveedor informal para recrear la sincronización limpia
            $book->entries()->where(function ($q) {
                $q->where('is_manual_override', false)
                  ->orWhereRaw('UPPER(supplier_name) LIKE ?', ['%INFORMAL%']);
            })->delete();

            $rawRows = collect();

            // Preservar y reinsertar las entradas manuales del usuario
            foreach ($manualEntries as $manualEntry) {
                $rawRows->push([
                    'type' => $manualEntry->retention_id ? 'retention' : 'invoice',
                    'date' => $manualEntry->operation_date ? $manualEntry->operation_date->format('Y-m-d') : $startDate,
                    'invoice_id' => $manualEntry->invoice_id,
                    'retention_id' => $manualEntry->retention_id,
                    'rif' => $manualEntry->rif,
                    'supplier_name' => $manualEntry->supplier_name,
                    'import_planilla_number' => $manualEntry->import_planilla_number,
                    'import_dossier_number' => $manualEntry->import_dossier_number,
                    'invoice_number' => $manualEntry->invoice_number,
                    'control_number' => $manualEntry->control_number,
                    'debit_note_number' => $manualEntry->debit_note_number,
                    'credit_note_number' => $manualEntry->credit_note_number,
                    'affected_invoice_number' => $manualEntry->affected_invoice_number,
                    'document_type' => $manualEntry->document_type,
                    'transaction_type' => $manualEntry->transaction_type,
                    'total_purchases_with_vat' => (float) $manualEntry->total_purchases_with_vat,
                    'exempt_purchases' => (float) $manualEntry->exempt_purchases,
                    'general_taxable_base' => (float) $manualEntry->general_taxable_base,
                    'general_aliquot' => (float) $manualEntry->general_aliquot,
                    'general_vat' => (float) $manualEntry->general_vat,
                    'reduced_taxable_base' => (float) $manualEntry->reduced_taxable_base,
                    'reduced_aliquot' => (float) $manualEntry->reduced_aliquot,
                    'reduced_vat' => (float) $manualEntry->reduced_vat,
                    'vat_withheld' => (float) $manualEntry->vat_withheld,
                    'withholding_receipt_number' => $manualEntry->withholding_receipt_number,
                    'is_manual_override' => true,
                ]);
            }

            // Agregar Facturas como 01-REG
            foreach ($invoices as $inv) {
                // Si la factura fue excluida de este libro, no agregarla automáticamente
                if (in_array($inv->id, $excludedInvoiceIds, true) || in_array((string) $inv->invoice_number, $excludedInvoiceNumbers, true)) {
                    continue;
                }
                // Si la factura ya fue editada o incorporada manualmente en este libro, evitar duplicarla
                if (in_array($inv->id, $manualInvoiceIds, true)) {
                    continue;
                }
                $supplierNameUpper = strtoupper(trim((string) ($inv->supplier?->name ?? '')));
                $supplierSocialUpper = strtoupper(trim((string) ($inv->supplier?->social_reason ?? '')));
                if (str_contains($supplierNameUpper, 'INFORMAL') || str_contains($supplierSocialUpper, 'INFORMAL')) {
                    continue;
                }

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
                    'supplier_name' => $inv->supplier?->social_reason ?: ($inv->supplier?->name ?: 'PROVEEDOR VARIOS'),
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

            // Agregar Comprobantes de Retención de IVA como 05-RET (según normativa SENIAT en comprobantes de retención no se duplica la base/IVA/exento de la compra)
            foreach ($retentions as $ret) {
                // Si la retención fue excluida de este libro, no agregarla automáticamente
                if (in_array($ret->id, $excludedRetentionIds, true)) {
                    continue;
                }
                // Si la retención ya fue editada o incorporada manualmente en este libro, evitar duplicarla
                if (in_array($ret->id, $manualRetentionIds, true)) {
                    continue;
                }
                $retSupplierNameUpper = strtoupper(trim((string) ($ret->supplier?->name ?? '')));
                $retSupplierSocialUpper = strtoupper(trim((string) ($ret->supplier?->social_reason ?? '')));
                if (str_contains($retSupplierNameUpper, 'INFORMAL') || str_contains($retSupplierSocialUpper, 'INFORMAL')) {
                    continue;
                }

                $affectedInvoices = $ret->invoices;
                $affectedInvoiceNum = $affectedInvoices->isNotEmpty()
                    ? $affectedInvoices->pluck('invoice_number')->filter()->implode(', ')
                    : null;
                $affectedControlNum = $affectedInvoices->isNotEmpty()
                    ? $affectedInvoices->pluck('control_number')->filter()->implode(', ')
                    : null;

                $rawRows->push([
                    'type' => 'retention',
                    'date' => $ret->date ? $ret->date->format('Y-m-d') : $startDate,
                    'invoice_id' => $affectedInvoices->first()?->id,
                    'retention_id' => $ret->id,
                    'rif' => $ret->supplier?->rif ?? 'N/A',
                    'supplier_name' => $ret->supplier?->social_reason ?: ($ret->supplier?->name ?: 'PROVEEDOR VARIOS'),
                    'import_planilla_number' => null,
                    'import_dossier_number' => null,
                    'invoice_number' => null,
                    'control_number' => $affectedControlNum ?: null,
                    'debit_note_number' => null,
                    'credit_note_number' => null,
                    'affected_invoice_number' => $affectedInvoiceNum,
                    'document_type' => 'RETENCIÓN',
                    'transaction_type' => '05-RET',
                    'total_purchases_with_vat' => 0.00,
                    'exempt_purchases' => 0.00,
                    'general_taxable_base' => 0.00,
                    'general_aliquot' => 0.00,
                    'general_vat' => 0.00,
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
                    'is_manual_override' => $item['is_manual_override'] ?? false,
                ]);
            }

            // Actualizar snapshot de enable_zoom según configuración actual
            $generalSetting = GeneralSetting::first();
            $book->update([
                'enable_zoom_snapshot' => (bool) ($generalSetting?->enable_zoom ?? false),
            ]);

            $this->reorderOperations($book);
            $this->recalculateTotals($book);
        });

        return $this->getBookById($book->id);
    }

    public function updateEntry(PurchaseBook $book, int $entryId, array $data): PurchaseBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede editar una entrada de un libro cerrado.");
        }

        $entry = $book->entries()->where('id', $entryId)->firstOrFail();

        $data['is_manual_override'] = true;
        $entry->update($data);

        $this->reorderOperations($book);
        $this->recalculateTotals($book);

        return $entry->fresh();
    }

    public function storeEntry(PurchaseBook $book, array $data): PurchaseBookEntry
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede agregar entradas a un libro cerrado.");
        }

        $data['purchase_book_id'] = $book->id;
        $data['operation_number'] = 999999;
        $data['is_manual_override'] = true;

        $entry = PurchaseBookEntry::create($data);

        // Si la factura estaba previamente excluida y el usuario la vuelve a agregar explícitamente, remover la exclusión
        if (!empty($data['invoice_id']) || !empty($data['invoice_number'])) {
            PurchaseBookExcludedInvoice::where('purchase_book_id', $book->id)
                ->where(function ($q) use ($data) {
                    if (!empty($data['invoice_id'])) {
                        $q->where('invoice_id', $data['invoice_id']);
                    }
                    if (!empty($data['invoice_number'])) {
                        $q->orWhere('invoice_number', $data['invoice_number']);
                    }
                })->delete();
        }

        $this->reorderOperations($book);
        $this->recalculateTotals($book);

        return $entry->fresh();
    }

    public function deleteEntry(PurchaseBook $book, int $entryId, bool $deleteInvoiceFromSystem = false): bool
    {
        if ($book->isClosed()) {
            throw new \DomainException("No se puede eliminar entradas de un libro cerrado.");
        }

        $entry = $book->entries()->where('id', $entryId)->firstOrFail();
        $invoiceId = $entry->invoice_id;
        $retentionId = $entry->retention_id;
        $invoiceNumber = $entry->invoice_number;
        $controlNumber = $entry->control_number;

        // Registrar la exclusión del libro para que sincronizaciones posteriores no vuelvan a insertarla sola
        PurchaseBookExcludedInvoice::firstOrCreate([
            'purchase_book_id' => $book->id,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
        ], [
            'control_number' => $controlNumber,
            'retention_id' => $retentionId,
        ]);

        // Si el usuario solicitó eliminar también la factura del sistema
        if ($deleteInvoiceFromSystem && $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                // Si la factura tiene pagos registrados, no se puede forzar borrado total
                if (!$invoice->payments()->exists()) {
                    $invoice->update(['status' => 'deleted']);
                }
            }
        }

        $deleted = $entry->delete();

        $this->reorderOperations($book);
        $this->recalculateTotals($book);

        return $deleted;
    }

    public function reorderOperations(PurchaseBook $book): void
    {
        $entries = $book->entries()
            ->reorder()
            ->orderBy('operation_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $op = 1;
        foreach ($entries as $e) {
            if ($e->operation_number !== $op) {
                $e->update(['operation_number' => $op]);
            }
            $op++;
        }
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
            ->whereRaw('UPPER(supplier_name) NOT LIKE ?', ['%INFORMAL%'])
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

    public function searchInvoices(string $term): \Illuminate\Support\Collection
    {
        $cleanTerm = trim($term);
        if (empty($cleanTerm)) {
            return collect();
        }

        return Invoice::with(['supplier', 'retention'])
            ->whereDoesntHave('supplier', function ($q) {
                $q->whereRaw('UPPER(name) LIKE ?', ['%INFORMAL%'])
                  ->orWhereRaw('UPPER(social_reason) LIKE ?', ['%INFORMAL%']);
            })
            ->where(function ($q) use ($cleanTerm) {
                $q->where('invoice_number', 'LIKE', "%{$cleanTerm}%")
                  ->orWhere('control_number', 'LIKE', "%{$cleanTerm}%");
            })
            ->orderBy('created_invoice_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($inv) {
                $supplier = $inv->supplier;
                $isZoom = str_contains(strtoupper((string) $supplier?->name), 'ZOOM') ||
                    str_contains(strtoupper((string) $supplier?->social_reason), 'ZOOM');

                $taxableBase = (float) ($inv->taxable_base ?? 0);
                $taxAmount = (float) ($inv->tax_amount ?? 0);
                $exemptAmount = (float) ($inv->exempt_amount ?? 0);
                $totalAmount = (float) ($inv->total_amount ?? 0);

                $hasReduced = false;
                $reducedBase = 0.00;
                $reducedVat = 0.00;
                $generalBase = $taxableBase;
                $generalVat = $taxAmount;

                if ($isZoom || ($taxableBase > 0 && round($taxAmount / $taxableBase, 2) === 0.08)) {
                    $hasReduced = true;
                    $reducedBase = $taxableBase;
                    $reducedVat = $taxAmount;
                    $generalBase = 0.00;
                    $generalVat = 0.00;
                }

                return [
                    'id' => $inv->id,
                    'invoice_number' => (string) $inv->invoice_number,
                    'control_number' => (string) ($inv->control_number ?? ''),
                    'created_invoice_date' => $inv->created_invoice_date ? $inv->created_invoice_date->format('Y-m-d') : null,
                    'rif' => PurchaseBookEntry::formatRif($supplier?->rif),
                    'supplier_name' => $supplier?->social_reason ?: ($supplier?->name ?: 'PROVEEDOR VARIOS'),
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
                    'vat_withheld' => (float) ($inv->retention?->total_withheld_amount ?? 0),
                    'withholding_receipt_number' => (string) ($inv->retention?->number ?? ''),
                ];
            });
    }
}
