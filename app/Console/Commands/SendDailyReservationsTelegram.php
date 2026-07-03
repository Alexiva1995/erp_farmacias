<?php

namespace App\Console\Commands;

use App\Models\FixedSchedule;
use App\Models\Reservation;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyReservationsTelegram extends Command
{
    protected $signature = 'telegram:send-daily-reservations';
    protected $description = 'Envía un consolidado de las reservaciones y horarios fijos del día al canal de Telegram';

    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    public function handle(): int
    {
        // Solo ejecutar en bases de datos de alquiler de canchas / reservas
        $dbName = config('database.connections.mysql.database');
        $botType = env('TELEGRAM_BOT_TYPE');
        
        $isCanchas = str_contains($dbName, 'golclub') || 
                     str_contains($dbName, 'cancha') || 
                     str_contains($dbName, 'reserva') || 
                     $botType === 'canchas';

        if (!$isCanchas) {
            $this->info('Comando omitido: Este entorno no es de alquiler de canchas/reservas.');
            return Command::SUCCESS;
        }

        $today          = Carbon::today()->toDateString();
        $todayFormatted = Carbon::today()->format('d/m/Y');
        $dayOfWeek      = Carbon::today()->dayOfWeekIso;

        // 1. Reservaciones confirmadas del día
        $reservations = Reservation::with('court')
            ->whereDate('date', $today)
            ->whereIn('status', ['verified', 'in_progress', 'completed'])
            ->get();

        // 2. Horarios fijos activos para este día de la semana (sin excepciones hoy)
        $fixedSchedules = FixedSchedule::with('court')
            ->where('day_of_week', $dayOfWeek)
            ->whereDoesntHave('exceptions', function ($query) use ($today) {
                $query->where('date', $today);
            })
            ->get();

        // Unificar en colección ordenada por cancha y hora
        $agenda = collect();

        foreach ($reservations as $r) {
            $agenda->push([
                'court_name'  => $r->court->name,
                'start_time'  => $r->start_time,
                'end_time'    => $r->end_time,
                'client_name' => $r->client_name,
                'is_fixed'    => false,
            ]);
        }

        foreach ($fixedSchedules as $f) {
            $agenda->push([
                'court_name'  => $f->court->name,
                'start_time'  => $f->start_time,
                'end_time'    => $f->end_time,
                'client_name' => $f->client_name,
                'is_fixed'    => true,
            ]);
        }

        // Cabecera del mensaje
        $message = "📅 *Agenda del día - {$todayFormatted}*\n\n";

        if ($agenda->isEmpty()) {
            $message .= "_No hay reservas ni horarios fijos programados para hoy._";
        } else {
            // Agrupar por cancha y ordenar internamente por hora
            $grouped = $agenda->groupBy('court_name')->sortKeys();

            foreach ($grouped as $courtName => $items) {
                $message .= "*{$courtName}*\n";
                $sortedItems = $items->sortBy('start_time')->values();
                foreach ($sortedItems as $item) {
                    $rStart   = Carbon::parse($item['start_time'])->format('g:i A');
                    $rEnd     = Carbon::parse($item['end_time'])->format('g:i A');
                    $fixedTag = $item['is_fixed'] ? ' 🔄' : '';
                    $message .= "{$rStart} a {$rEnd} - {$item['client_name']}{$fixedTag}\n";
                }
                $message .= "\n";
            }
        }

        $this->telegramService->sendMessage($message);
        $this->info("Agenda del día enviada a Telegram correctamente.");

        return Command::SUCCESS;
    }
}
