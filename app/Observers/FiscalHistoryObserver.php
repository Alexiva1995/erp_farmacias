<?php

namespace App\Observers;

use App\Models\FiscalHistory;
use App\Services\Fiscal\FiscalZReportService;
use Carbon\Carbon;
use Throwable;

class FiscalHistoryObserver
{
    /**
     * Handle the FiscalHistory "created" event.
     */
    public function created(FiscalHistory $history): void
    {
        $this->syncDailyZReport($history);
    }

    /**
     * Handle the FiscalHistory "updated" event.
     */
    public function updated(FiscalHistory $history): void
    {
        $this->syncDailyZReport($history);
    }

    /**
     * Handle the FiscalHistory "deleted" event.
     */
    public function deleted(FiscalHistory $history): void
    {
        $this->syncDailyZReport($history);
    }

    private function syncDailyZReport(FiscalHistory $history): void
    {
        try {
            $date = $history->invoice_date 
                ? Carbon::parse($history->invoice_date)->format('Y-m-d')
                : ($history->created_at ? Carbon::parse($history->created_at)->format('Y-m-d') : Carbon::today()->format('Y-m-d'));

            $service = app(FiscalZReportService::class);
            $service->generateForDate($date, null, true);
        } catch (Throwable $e) {
            \Log::warning('[FiscalHistoryObserver] Error al sincronizar Reporte Z diario: ' . $e->getMessage());
        }
    }
}
