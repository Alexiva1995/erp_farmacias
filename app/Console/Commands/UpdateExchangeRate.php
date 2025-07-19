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
    protected $description = 'Comando para actualizar valor del dolar BCV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /*$response = Http::get('https://ve.dolarapi.com/v1/dolares');

        $data = [
            "currency_code" => "USD",
            "rate"          => $response[0]['promedio'],
        ];

        return ExchangeRate::create($data);*/

        $this->info("DOOM ETERNAL");
    }
}
