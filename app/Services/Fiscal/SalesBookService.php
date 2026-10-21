<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\SalesBookContract;
use App\Exports\SalesBookExport;
use App\Models\GeneralSetting;
use App\Models\SalesBook;
use App\Models\SalesBookEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesBookService
{
    public function __construct(
        protected SalesBookContract $salesBookRepository
    ) {}

    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->salesBookRepository->getBooks($filters, $perPage);
    }

    public function findOrCreateCurrentBook(): SalesBook
    {
        $now = now();
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $fortnight = $isSpecialTaxpayer ? ($now->day <= 15 ? 1 : 2) : null;

        return $this->salesBookRepository->findOrCreateBook($now->year, $now->month, $fortnight);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): SalesBook
    {
        return $this->salesBookRepository->findOrCreateBook($year, $month, $fortnight);
    }

    public function getBookById(int $id): SalesBook
    {
        return $this->salesBookRepository->getBookById($id);
    }

    public function syncTransactions(int $id): SalesBook
    {
        $book = $this->salesBookRepository->getBookById($id);
        return $this->salesBookRepository->syncTransactions($book);
    }

    public function updateEntry(int $bookId, int $entryId, array $data): SalesBookEntry
    {
        $book = $this->salesBookRepository->getBookById($bookId);
        return $this->salesBookRepository->updateEntry($book, $entryId, $data);
    }

    public function storeEntry(int $bookId, array $data): SalesBookEntry
    {
        $book = $this->salesBookRepository->getBookById($bookId);
        return $this->salesBookRepository->storeEntry($book, $data);
    }

    public function deleteEntry(int $bookId, int $entryId): bool
    {
        $book = $this->salesBookRepository->getBookById($bookId);
        return $this->salesBookRepository->deleteEntry($book, $entryId);
    }

    public function closeBook(int $id, int $userId): SalesBook
    {
        $book = $this->salesBookRepository->getBookById($id);
        return $this->salesBookRepository->closeBook($book, $userId);
    }

    public function exportExcel(int $id): BinaryFileResponse
    {
        $book = $this->salesBookRepository->getBookById($id);
        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $filename = "libro_ventas_{$periodSuffix}.xlsx";

        return Excel::download(new SalesBookExport($book), $filename);
    }

    public function exportPdf(int $id)
    {
        $book = $this->salesBookRepository->getBookById($id);
        $settings = GeneralSetting::first() ?? new GeneralSetting();
        $enableZoom = $book->isClosed()
            ? (bool) $book->enable_zoom_snapshot
            : (bool) ($settings->enable_zoom ?? false);

        $periodSuffix = $book->period_type->value === 'fortnightly'
            ? "Q{$book->fortnight}_{$book->month}_{$book->year}"
            : "M{$book->month}_{$book->year}";

        $pdf = Pdf::loadView('pdf.sales_book', [
            'book' => $book,
            'settings' => $settings,
            'enableZoom' => $enableZoom,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("libro_ventas_{$periodSuffix}.pdf");
    }
}
