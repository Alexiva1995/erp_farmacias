<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\VatRetentionBookContract;
use App\Exports\VatRetentionBookExport;
use App\Models\GeneralSetting;
use App\Models\VatRetentionBook;
use App\Models\VatRetentionBookEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VatRetentionBookService
{
    public function __construct(
        protected VatRetentionBookContract $repository
    ) {}

    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getBooks($filters, $perPage);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): VatRetentionBook
    {
        return $this->repository->findOrCreateBook($year, $month, $fortnight);
    }

    public function findOrCreateCurrentBook(): VatRetentionBook
    {
        $now = Carbon::now();
        $fortnight = $now->day <= 15 ? 1 : 2;

        return $this->repository->findOrCreateBook($now->year, $now->month, $fortnight);
    }

    public function syncExistingRetentionPeriods(): void
    {
        $this->repository->syncExistingRetentionPeriods();
    }

    public function getBookById(int $id): VatRetentionBook
    {
        return $this->repository->getBookById($id);
    }

    public function syncTransactions(int $id): VatRetentionBook
    {
        $book = $this->repository->getBookById($id);
        return $this->repository->syncTransactions($book);
    }

    public function updateEntry(int $id, int $entryId, array $data): VatRetentionBookEntry
    {
        $book = $this->repository->getBookById($id);
        return $this->repository->updateEntry($book, $entryId, $data);
    }

    public function closeBook(int $id, int $userId): VatRetentionBook
    {
        $book = $this->repository->getBookById($id);
        return $this->repository->closeBook($book, $userId);
    }

    public function reopenBook(int $id): VatRetentionBook
    {
        $book = $this->repository->getBookById($id);
        return $this->repository->reopenBook($book);
    }

    /**
     * Exporta el archivo TXT oficial para el portal SENIAT (16 columnas separadas por tabulador, sin cabecera).
     */
    public function exportTxt(int $id): Response
    {
        $book = $this->repository->getBookById($id);

        if ($book->isOpen()) {
            $book = $this->repository->syncTransactions($book);
        }

        $lines = [];
        foreach ($book->entries as $entry) {
            $lines[] = $entry->toSeniatTxtLine();
        }

        // Estándar de texto Windows con CRLF
        $content = implode("\r\n", $lines) . (count($lines) > 0 ? "\r\n" : "");

        $periodTypeValue = is_object($book->period_type) ? $book->period_type->value : (string) $book->period_type;
        $periodSuffix = ($periodTypeValue === 'fortnightly')
            ? sprintf('%04d%02d_Q%d', $book->year, $book->month, $book->fortnight ?: 1)
            : sprintf('%04d%02d', $book->year, $book->month);

        $filename = "TxtImpuesto{$periodSuffix}.txt";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Exporta el reporte a Excel con diseño optimizado para impresión y fondo blanco.
     */
    public function exportExcel(int $id): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $book = $this->repository->getBookById($id);

        if ($book->isOpen()) {
            $book = $this->repository->syncTransactions($book);
        }

        $periodTypeValue = is_object($book->period_type) ? $book->period_type->value : (string) $book->period_type;
        $periodSuffix = ($periodTypeValue === 'fortnightly')
            ? sprintf('%04d%02d_Q%d', $book->year, $book->month, $book->fortnight ?: 1)
            : sprintf('%04d%02d', $book->year, $book->month);

        $filename = "reporte_retenciones_iva_{$periodSuffix}.xlsx";

        return Excel::download(new VatRetentionBookExport($book), $filename);
    }

    /**
     * Exporta el reporte a PDF en formato Landscape con fondo blanco para optimizar tinta.
     */
    public function exportPdf(int $id)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $book = $this->repository->getBookById($id);

        if ($book->isOpen()) {
            $book = $this->repository->syncTransactions($book);
        }

        $settings = GeneralSetting::first() ?? new GeneralSetting();

        $periodTypeValue = is_object($book->period_type) ? $book->period_type->value : (string) $book->period_type;
        $periodSuffix = ($periodTypeValue === 'fortnightly')
            ? sprintf('%04d%02d_Q%d', $book->year, $book->month, $book->fortnight ?: 1)
            : sprintf('%04d%02d', $book->year, $book->month);

        $pdf = Pdf::loadView('pdf.vat_retention_book', [
            'book' => $book,
            'settings' => $settings,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("reporte_retenciones_iva_{$periodSuffix}.pdf");
    }
}
