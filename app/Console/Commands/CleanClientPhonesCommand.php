<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanClientPhonesCommand extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'clients:clean-phones {--from-date=2026-09-01 : Fecha de inicio para considerar compras}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Limpia y valida los números telefónicos de clientes que tienen compras desde septiembre 2026 en adelante';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $fromDate = $this->option('from-date');
        $this->info("Iniciando limpieza y normalización de teléfonos desde {$fromDate}...");

        $clientIds = \App\Models\Order::where('status', \App\Models\Order::COMPLETED)
            ->where('order_date', '>=', $fromDate)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        $this->info("Clientes encontrados con compras desde {$fromDate}: " . $clientIds->count());

        $clients = \App\Models\Client::whereIn('id', $clientIds)->get();

        $cleanedCount = 0;
        $invalidCount = 0;

        foreach ($clients as $client) {
            $rawPhone = $client->phone;
            if (empty($rawPhone)) {
                $invalidCount++;
                continue;
            }

            // Extraer solo dígitos numéricos
            $digits = preg_replace('/[^0-9]/', '', (string) $rawPhone);

            // Validar longitud y estructura
            if (strlen($digits) < 10) {
                // Teléfono inválido o incompleto
                $invalidCount++;
                continue;
            }

            // Normalización para formato nacional o internacional
            if (str_starts_with($digits, '0') && strlen($digits) === 11) {
                // Ej. 04141234567 -> 0414-1234567 o mantener dígitos limpios
                $normalized = $digits;
            } elseif (str_starts_with($digits, '58') && strlen($digits) === 12) {
                $normalized = '0' . substr($digits, 2);
            } elseif (strlen($digits) === 10) {
                $normalized = '0' . $digits;
            } else {
                $normalized = $digits;
            }

            if ($client->phone !== $normalized) {
                $client->phone = $normalized;
                $client->save();
                $cleanedCount++;
            }
        }

        $this->info("Proceso completado con éxito.");
        $this->info("- Clientes con teléfono normalizado: {$cleanedCount}");
        $this->info("- Clientes con teléfono faltante o inválido (< 10 dígitos): {$invalidCount}");

        return self::SUCCESS;
    }
}
