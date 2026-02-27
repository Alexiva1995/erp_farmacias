<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-exchange-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para actualizar valor del dolar BCV y el Euro en BS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ── Dólar BCV → BS ──────────────────────────────────────────────────
        $responseBCV = Http::get('https://ve.dolarapi.com/v1/dolares');

        ExchangeRate::updateOrCreate(
            ['currency_code' => 'BS'],
            ['rate' => $responseBCV[0]['promedio'], 'source' => null]
        );

        $this->info("Dólar BCV actualizado: {$responseBCV[0]['promedio']} BS");

        // ── Euro → BS (via EUR/VES) ──────────────────────────────────────────
        $responseEUR = Http::get('https://open.er-api.com/v6/latest/EUR');

        $eurToVes = $responseEUR->json('rates.VES');

        ExchangeRate::updateOrCreate(
            ['currency_code' => 'EUR'],
            ['rate' => $eurToVes, 'source' => null]
        );

        $this->info("Euro actualizado: {$eurToVes} BS");

        $this->info("Euro actualizado: {$eurToVes} BS");
    }
}
