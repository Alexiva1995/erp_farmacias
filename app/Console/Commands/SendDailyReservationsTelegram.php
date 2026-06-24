<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyReservationsTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-daily-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un consolidado de las reservaciones del día en curso al canal de Telegram';

    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $todayFormatted = Carbon::today()->format('d/m/Y');

        // Consultar reservaciones activas (pending, verified, in_progress, completed) de hoy
        $reservations = Reservation::with('court')
            ->whereDate('date', $today)
            ->whereIn('status', ['pending', 'verified', 'in_progress', 'completed'])
            ->orderBy('start_time')
            ->get();

        $message = "📅 *Agenda de Reservas - Hoy {$todayFormatted}*\n\n";

        if ($reservations->isEmpty()) {
            $message .= "No hay reservas registradas para el día de hoy. ⚽";
        } else {
            $message .= "Total de reservas programadas: *{$reservations->count()}*\n\n";
            
            foreach ($reservations as $index => $res) {
                $num = $index + 1;
                $statusEmoji = match($res->status) {
                    'verified' => '✅ (Confirmada)',
                    'in_progress' => '🏃 (En curso)',
                    'completed' => '🏁 (Finalizada)',
                    default => '⏳ (Pendiente)'
                };
                
                $startTime = substr($res->start_time, 0, 5);
                $endTime = substr($res->end_time, 0, 5);
                $weeklyTag = $res->request_weekly_fixed ? " 🔄 *[Solicita Fijo Semanal]*" : "";
                
                $message .= "{$num}. *{$res->court->name}*\n";
                $message .= "   🕒 *Hora:* {$startTime} a {$endTime}\n";
                $message .= "   👤 *Cliente:* {$res->client_name} ({$res->identification})\n";
                $message .= "   📞 *WhatsApp:* {$res->client_whatsapp}\n";
                $message .= "   🏷️ *Estado:* {$statusEmoji}{$weeklyTag}\n\n";
            }
        }

        $this->telegramService->sendMessage($message);
        $this->info("Consolidado de reservas enviado a Telegram correctamente.");

        return Command::SUCCESS;
    }
}
