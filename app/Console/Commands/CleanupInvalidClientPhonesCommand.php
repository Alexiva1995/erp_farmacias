<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class CleanupInvalidClientPhonesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:cleanup-phones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia teléfonos inválidos, incompletos o con patrones ficticios en los clientes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando limpieza de teléfonos de clientes...');

        $clients = Client::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $cleanedCount = 0;

        foreach ($clients as $client) {
            $rawPhone = trim($client->phone);
            $cleanDigits = preg_replace('/[^0-9]/', '', $rawPhone);

            // Validar si es un teléfono venezolano válido (0412, 0414, 0424, 0416, 0426 o fijo 02XX de 11 dígitos o 10 sin 0)
            $isRepeated = preg_match('/^(\d)\1+$/', $cleanDigits);
            $isValidVenezuelan = preg_match('/^(0?)(412|414|424|416|426|2\d{2})\d{7}$/', $cleanDigits);
            $isDummy = in_array($cleanDigits, ['1234567890', '12345678', '01234567890', '0000000000', '00000000000']);

            if ($isRepeated || !$isValidVenezuelan || $isDummy) {
                $client->phone = null;
                $client->save();
                $cleanedCount++;
                $this->line("Cliente ID #{$client->id} ({$client->name}): teléfono '{$rawPhone}' limpiado a NULL.");
            }
        }

        $this->info("Proceso completado. Total de teléfonos limpiados: {$cleanedCount}");

        return 0;
    }
}