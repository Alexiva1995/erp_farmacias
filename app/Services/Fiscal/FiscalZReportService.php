<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\FiscalZReportRepositoryInterface;
use App\Models\FiscalHistory;
use App\Models\FiscalZReport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiscalZReportService
{
    public const DEFAULT_START_NUMBER = 740;

    public function __construct(
        private FiscalZReportRepositoryInterface $repository
    ) {}

    /**
     * Obtiene el listado paginado con filtros.
     */
    public function getReports(array $filters, int $perPage = 10, ?string $sortBy = 'report_date', string $orderBy = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getFilteredPaginated($filters, $perPage, $sortBy, $orderBy);
    }

    /**
     * Obtiene estadísticas agregadas de los reportes.
     */
    public function getSummaryStats(array $filters): array
    {
        return $this->repository->getSummaryStats($filters);
    }

    /**
     * Obtiene un reporte por ID.
     */
    public function getReportById(int $id): ?FiscalZReport
    {
        return $this->repository->findById($id);
    }

    /**
     * Genera o recalcula el Reporte Z de una fecha específica.
     * 
     * @param string $date Fecha en formato Y-m-d
     * @param int|null $customReportNumber Número asignado (opcional)
     * @param bool $force Recalcular si ya existe
     * @return FiscalZReport
     */
    public function generateForDate(string $date, ?int $customReportNumber = null, bool $force = false): FiscalZReport
    {
        $existing = $this->repository->findByDate($date);
        if ($existing && !$force) {
            return $existing;
        }

        // Consultar ventas fiscales del día en fiscal_history
        $records = FiscalHistory::whereDate('invoice_date', $date)
            ->orWhere(function ($q) use ($date) {
                $q->whereNull('invoice_date')->whereDate('created_at', $date);
            })
            ->orderBy('id', 'asc')
            ->get();

        $invoicesCount = $records->count();
        $exemptAmount = round((float) $records->sum('exempt_amount'), 2);
        $base16Amount = round((float) $records->sum('taxable_amount'), 2);
        $ivaAmount = round((float) $records->sum('iva_amount'), 2);

        // IGTF / SPE
        $igtfAmount = round((float) $records->sum('spe_surcharge_amount'), 2);
        $igtfBaseAmount = round((float) $records->where('spe', true)->sum('taxable_amount'), 2);
        if ($igtfBaseAmount === 0.0 && $igtfAmount > 0) {
            $igtfBaseAmount = round((float) $records->where('spe_surcharge_amount', '>', 0)->sum(function ($r) {
                return (float)$r->exempt_amount + (float)$r->taxable_amount + (float)$r->iva_amount;
            }), 2);
        }

        // Total en Bs: Exento + Base 16% + IVA 16% + IGTF 3%
        $calculatedTotal = round($exemptAmount + $base16Amount + $ivaAmount + $igtfAmount, 2);
        $totalAmount = round((float) ($records->sum('total_amount') ?: $calculatedTotal), 2);

        // Facturas de inicio y fin
        $firstInvoice = $records->first();
        $lastInvoice = $records->last();
        $firstNumber = $firstInvoice ? ($firstInvoice->invoice_number ?: ('#' . ($firstInvoice->fiscal_id ?: $firstInvoice->id))) : null;
        $lastNumber = $lastInvoice ? ($lastInvoice->invoice_number ?: ('#' . ($lastInvoice->fiscal_id ?: $lastInvoice->id))) : null;

        // Determinar número consecutivo de Reporte Z
        $reportNumber = $existing?->report_number;
        if (!$reportNumber) {
            if ($customReportNumber !== null) {
                $reportNumber = $customReportNumber;
            } else {
                $lastNumberInDb = $this->repository->getLastReportNumber();
                $reportNumber = $lastNumberInDb ? ($lastNumberInDb + 1) : self::DEFAULT_START_NUMBER;
            }
        }

        $openingTime = $firstInvoice && $firstInvoice->created_at ? Carbon::parse($firstInvoice->created_at)->format('H:i:s') : '08:00:00';
        $closingTime = $lastInvoice && $lastInvoice->created_at ? Carbon::parse($lastInvoice->created_at)->format('H:i:s') : '23:59:59';

        $data = [
            'report_number'        => $reportNumber,
            'report_date'          => $date,
            'opening_time'         => $openingTime,
            'closing_time'         => $closingTime,
            'first_invoice_number' => $firstNumber,
            'last_invoice_number'  => $lastNumber,
            'invoices_count'       => $invoicesCount,
            'exempt_amount'        => $exemptAmount,
            'base_16_amount'       => $base16Amount,
            'iva_amount'           => $ivaAmount,
            'igtf_base_amount'     => $igtfBaseAmount,
            'igtf_amount'          => $igtfAmount,
            'total_amount'         => $totalAmount,
            'status'               => 'closed',
        ];

        return $this->repository->updateOrCreateByDate($date, $data);
    }

    /**
     * Genera todos los reportes Z para los días de un mes y año dados.
     * 
     * @param int $year
     * @param int $month
     * @param int $startNumber Número inicial para el primer reporte si no existen previos
     * @param bool $force Recalcular existentes
     * @return array
     */
    public function generateForMonth(int $year, int $month, int $startNumber = self::DEFAULT_START_NUMBER, bool $force = false): array
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $today = Carbon::today();

        // No generar más allá del día actual si es el mes en curso
        if ($endDate->isAfter($today)) {
            $endDate = $today;
        }

        $generated = [];
        $currentDate = $startDate->copy();

        // Determinar siguiente número consecutivo
        $nextNumber = $this->repository->getLastReportNumber();
        $nextNumber = $nextNumber ? ($nextNumber + 1) : $startNumber;

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $existing = $this->repository->findByDate($dateStr);

            $assignedNumber = null;
            if (!$existing) {
                $assignedNumber = $nextNumber++;
            }

            $report = $this->generateForDate($dateStr, $assignedNumber, $force);
            $generated[] = $report;

            $currentDate->addDay();
        }

        return $generated;
    }
}
