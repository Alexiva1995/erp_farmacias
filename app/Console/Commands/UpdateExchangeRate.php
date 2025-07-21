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
        //$exchangeRate = new ExchangeRate;
        //$id = null;
        $response = Http::get('https://ve.dolarapi.com/v1/dolares');
        //$this->info($response[0]['promedio']);
        $data = [
            "currency_code" => "USD",
            "rate"          => $response[0]['promedio'],
            "source"        => null,
        ];

        ExchangeRate::create($data);
        $this->info("The BCV exchange rate has been created");
        /*if ($exchangeRate->where("currency_code", "USD")->first()) {
            $this->info("Existe un valor BCV");
            $this->info("The BCV exchange rate has been update");
        } else {
            ExchangeRate::create($data);
            $this->info("The BCV exchange rate has been created");
        }*/
    }
}
