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
            $rawPhone = trim((string) $client->phone);
            $cleanDigits = preg_replace('/[^0-9]/', '', $rawPhone);

            // 1. Número con un solo dígito repetido (ej: 5555555555, 0000000000, 1111111111)
            $isRepeated = preg_match('/^(\d)\1+$/', $cleanDigits);
            
            // 2. Longitud inválida (debe ser entre 10 y 12 dígitos)
            $isLengthInvalid = (strlen($cleanDigits) < 10 || strlen($cleanDigits) > 12);

            // 3. Estructura de teléfono venezolano válido (0412, 0414, 0424, 0416, 0426 o fijos 02XX de 11 dígitos, 10 sin 0 inicial, o con prefijo 58)
            $isValidVenezuelan = preg_match('/^(58)?(0?)(412|414|424|416|426|2\d{2})\d{7}$/', $cleanDigits);

            // 4. Patrones falsos o de prueba comunes
            $isDummy = in_array($cleanDigits, [
                '1234567890', '12345678', '01234567890', '0000000000', '00000000000',
                '123456789', '9876543210', '1111111111', '2222222222', '3333333333',
                '4444444444', '5555555555', '6666666666', '7777777777', '8888888888', '9999999999'
            ], true);

            if ($isRepeated || $isLengthInvalid || !$isValidVenezuelan || $isDummy) {
                $client->phone = null;
                $client->save();
                $cleanedCount++;
                $this->line(" -> Cliente ID #{$client->id} ({$client->name} {$client->last_name}): teléfono inválido '{$rawPhone}' limpiado a NULL.");
            }
        }

        $this->info("Proceso completado. Total de teléfonos limpiados: {$cleanedCount}");

        return 0;
    }
}