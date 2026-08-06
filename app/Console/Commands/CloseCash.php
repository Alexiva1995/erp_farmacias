<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CashClosing;
use App\Models\TelegramCommand;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseCash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-cash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierre diario consolidado de caja a medianoche con notificación por Telegram.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cashClosureService = app(\App\Services\CashClosure\CashClosureActionService::class);

        try {
            $cashClosureService->closeDailyCashClosure();
            $this->info('Cierre diario de caja ejecutado correctamente.');
        } catch (\Exception $e) {
            Log::error("Error al realizar el cierre de caja diario: {$e->getMessage()}");
            $this->error("Error en cierre diario: {$e->getMessage()}");
        }

        // Notificar el cierre general diario por Telegram a medianoche si está activo
        try {
            $isNotifyActive = TelegramCommand::where('module', 'generales')
                ->where('command', '/cierre_general')
                ->value('is_active') ?? true;

            if ($isNotifyActive) {
                $today = now()->toDateString();
                $todayClosings = CashClosing::whereDate('closing_date', $today)->get();

                $totalSales = $todayClosings->sum('total_sales');
                $totalUsd = $todayClosings->sum('total_usd');
                $totalCop = $todayClosings->sum('total_cop');
                $totalBs = $todayClosings->sum('total_bs');
                $totalClosingsCount = $todayClosings->count();

                $msg = "🌙 *[CIERRE GENERAL DIARIO - CONSOLIDADO MEDIANOCHE]* 🌙\n\n"
                     . "📅 *Fecha:* " . now()->format('d/m/Y') . "\n"
                     . "📊 *Total de Cierres de Turno:* {$totalClosingsCount}\n\n"
                     . "💰 *Ventas Totales Consolidadas:*\n"
                     . "• USD: $" . number_format((float) $totalUsd, 2) . "\n"
                     . "• COP: $" . number_format((float) $totalCop, 2) . "\n"
                     . "• BS: Bs " . number_format((float) $totalBs, 2) . "\n"
                     . "----------------------------------------\n"
                     . "💵 *Monto Total de Ventas:* $" . number_format((float) $totalSales, 2) . " USD\n\n"
                     . "✨ *Sistema de Cierre Diario completado a las 11:59 PM.*";

                $telegram = resolve(TelegramService::class);
                $telegram->sendToAdmin($msg);
            }
        } catch (\Exception $e) {
            Log::error("Error enviando notificación de Cierre General Diario a Telegram: {$e->getMessage()}");
        }
    }
}
