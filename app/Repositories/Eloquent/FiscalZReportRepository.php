<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FiscalZReportRepositoryInterface;
use App\Models\FiscalZReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FiscalZReportRepository implements FiscalZReportRepositoryInterface
{
    public function getFilteredPaginated(array $filters, int $perPage = 10, ?string $sortBy = 'report_date', string $orderBy = 'desc'): LengthAwarePaginator
    {
        $query = FiscalZReport::query();

        $this->applyFilters($query, $filters);

        $validSortColumns = [
            'id',
            'report_number',
            'report_date',
            'exempt_amount',
            'base_16_amount',
            'iva_amount',
            'igtf_base_amount',
            'igtf_amount',
            'total_amount',
            'invoices_count',
        ];

        $column = in_array($sortBy, $validSortColumns, true) ? $sortBy : 'report_date';
        $direction = strtolower($orderBy) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    public function getSummaryStats(array $filters): array
    {
        $query = FiscalZReport::query();

        $this->applyFilters($query, $filters);

        $stats = $query->selectRaw('
            COUNT(*) as total_reports,
            COALESCE(SUM(invoices_count), 0) as total_invoices,
            COALESCE(SUM(exempt_amount), 0) as total_exempt,
            COALESCE(SUM(base_16_amount), 0) as total_base_16,
            COALESCE(SUM(iva_amount), 0) as total_iva,
            COALESCE(SUM(igtf_base_amount), 0) as total_igtf_base,
            COALESCE(SUM(igtf_amount), 0) as total_igtf,
            COALESCE(SUM(total_amount), 0) as grand_total
        ')->first();

        return [
            'total_reports'   => (int) ($stats->total_reports ?? 0),
            'total_invoices'  => (int) ($stats->total_invoices ?? 0),
            'total_exempt'    => (float) ($stats->total_exempt ?? 0),
            'total_base_16'   => (float) ($stats->total_base_16 ?? 0),
            'total_iva'       => (float) ($stats->total_iva ?? 0),
            'total_igtf_base' => (float) ($stats->total_igtf_base ?? 0),
            'total_igtf'      => (float) ($stats->total_igtf ?? 0),
            'grand_total'     => (float) ($stats->grand_total ?? 0),
        ];
    }

    public function findById(int $id): ?FiscalZReport
    {
        return FiscalZReport::find($id);
    }

    public function findByNumber(int $number): ?FiscalZReport
    {
        return FiscalZReport::where('report_number', $number)->first();
    }

    public function findByDate(string $date): ?FiscalZReport
    {
        return FiscalZReport::whereDate('report_date', $date)->first();
    }

    public function getLastReportNumber(): ?int
    {
        return FiscalZReport::max('report_number');
    }

    public function updateOrCreateByDate(string $date, array $data): FiscalZReport
    {
        return FiscalZReport::updateOrCreate(
            ['report_date' => $date],
            $data
        );
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('report_number', 'like', "%{$q}%")
                    ->orWhere('first_invoice_number', 'like', "%{$q}%")
                    ->orWhere('last_invoice_number', 'like', "%{$q}%");
            });
        }

        if (!empty($filters['startDate'])) {
            $query->whereDate('report_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('report_date', '<=', $filters['endDate']);
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->whereYear('report_date', (int)$filters['year'])
                  ->whereMonth('report_date', (int)$filters['month']);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('report_date', (int)$filters['year']);
        }
    }
}
