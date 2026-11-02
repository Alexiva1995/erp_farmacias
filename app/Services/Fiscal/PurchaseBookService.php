<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\PurchaseBookContract;
use App\Exports\PurchaseBookExport;
use App\Models\GeneralSetting;
use App\Models\PurchaseBook;
use App\Models\PurchaseBookEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseBookService
{
    public function __construct(
        protected PurchaseBookContract $purchaseBookRepository
    ) {}

    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->purchaseBookRepository->getBooks($filters, $perPage);
    }

    public function findOrCreateCurrentBook(): PurchaseBook
    {
        $now = now();
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $fortnight = $isSpecialTaxpayer ? ($now->day <= 15 ? 1 : 2) : null;

        return $this->purchaseBookRepository->findOrCreateBook($now->year, $now->month, $fortnight);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): PurchaseBook
    {
        return $this->purchaseBookRepository->findOrCreateBook($year, $month, $fortnight);
    }

    public function getBookById(int $id, bool $autoSync = true): PurchaseBook
    {
        $book = $this->purchaseBookRepository->getBookById($id);

        if ($autoSync && $book->isOpen()) {
            try {
                $book = $this->purchaseBookRepository->syncTransactions($book);
            } catch (\Throwable $e) {
                Log::warning("No se pudo auto-sincronizar el libro de compras {$id}: {$e->getMessage()}");
            }
        }

        return $book;
    }

    public function syncTransactions(int $id): PurchaseBook
    {
        $book = $this->purchaseBookRepository->getBookById($id);
        return $this->purchaseBookRepository->syncTransactions($book);
    }

    public function updateEntry(int $bookId, int $entryId, array $data): PurchaseBookEntry
    {
        $book = $this->purchaseBookRepository->getBookById($bookId);
        return $this->purchaseBookRepository->updateEntry($book, $entryId, $data);
    }

    public function storeEntry(int $bookId, array $data): PurchaseBookEntry
    {
        $book = $this->purchaseBookRepository->getBookById($bookId);
        return $this->purchaseBookRepository->storeEntry($book, $data);
    }

    public function deleteEntry(int $bookId, int $entryId): bool
    {
        $book = $this->purchaseBookRepository->getBookById($bookId);
        return $this->purchaseBookRepository->deleteEntry($book, $entryId);
    }

    public function closeBook(int $id, int $userId): PurchaseBook
    {
        $book = $this->purchaseBookRepository->getBookById($id);
        return $this->purchaseBookRepository->closeBook($book, $userId);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        $book = $this->getBookById($id);
        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $filename = "libro_compras_{$periodSuffix}.xlsx";

        return Excel::download(new PurchaseBookExport($book), $filename);
    }

    public function exportPdf(int $id)
    {
        $book = $this->getBookById($id);
        $settings = GeneralSetting::first() ?? new GeneralSetting();
        $enableZoom = $book->isClosed()
            ? (bool) $book->enable_zoom_snapshot
            : (bool) ($settings->enable_zoom ?? false);

        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $pdf = Pdf::loadView('pdf.purchase_book', [
            'book' => $book,
            'settings' => $settings,
            'enableZoom' => $enableZoom,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("libro_compras_{$periodSuffix}.pdf");
    }

    public function searchInvoices(string $term): \Illuminate\Support\Collection
    {
        return $this->purchaseBookRepository->searchInvoices($term);
    }
}
