<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\InventoryBookContract;
use App\Exports\InventoryBookExport;
use App\Models\GeneralSetting;
use App\Models\InventoryBook;
use App\Models\InventoryBookEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryBookService
{
    public function __construct(
        protected InventoryBookContract $inventoryBookRepository
    ) {}

    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->inventoryBookRepository->getBooks($filters, $perPage);
    }

    public function findOrCreateCurrentBook(): InventoryBook
    {
        $now = now();
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $fortnight = $isSpecialTaxpayer ? ($now->day <= 15 ? 1 : 2) : null;

        return $this->inventoryBookRepository->findOrCreateBook($now->year, $now->month, $fortnight);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): InventoryBook
    {
        return $this->inventoryBookRepository->findOrCreateBook($year, $month, $fortnight);
    }

    public function getBookById(int $id): InventoryBook
    {
        return $this->inventoryBookRepository->getBookById($id);
    }

    public function syncTransactions(int $id): InventoryBook
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        return $this->inventoryBookRepository->syncTransactions($book);
    }

    public function updateEntry(int $bookId, int $entryId, array $data): InventoryBookEntry
    {
        $book = $this->inventoryBookRepository->getBookById($bookId);
        return $this->inventoryBookRepository->updateEntry($book, $entryId, $data);
    }

    public function toggleAdjustmentsAsWithdrawals(int $id, bool $include): InventoryBook
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        return $this->inventoryBookRepository->toggleAdjustmentsAsWithdrawals($book, $include);
    }

    public function closeBook(int $id, int $userId): InventoryBook
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        return $this->inventoryBookRepository->closeBook($book, $userId);
    }

    public function reopenBook(int $id): InventoryBook
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        return $this->inventoryBookRepository->reopenBook($book);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $filename = "libro_inventario_art177_{$periodSuffix}.xlsx";

        return Excel::download(new InventoryBookExport($book), $filename);
    }

    public function exportPdf(int $id)
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        $settings = GeneralSetting::first() ?? new GeneralSetting();

        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $pdf = Pdf::loadView('pdf.inventory_book', [
            'book' => $book,
            'settings' => $settings,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("libro_inventario_art177_{$periodSuffix}.pdf");
    }

    public function exportWithdrawalActPdf(int $id)
    {
        $book = $this->inventoryBookRepository->getBookById($id);
        $settings = GeneralSetting::first() ?? new GeneralSetting();

        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $pdf = Pdf::loadView('pdf.inventory_withdrawal_act', [
            'book' => $book,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("acta_desincorporacion_iva_{$periodSuffix}.pdf");
    }
}
