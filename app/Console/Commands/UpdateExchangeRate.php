<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
        $bcvRate = null;
        try {
            $responseBCV = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares/oficial');
            if ($responseBCV->successful() && !is_null($responseBCV->json('promedio'))) {
                $bcvRate = $responseBCV->json('promedio');
            } else {
                // Intento fallback con la lista general de dólares
                $responseBCVList = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares');
                if ($responseBCVList->successful() && is_array($responseBCVList->json())) {
                    foreach ($responseBCVList->json() as $item) {
                        if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                            $bcvRate = $item['promedio'] ?? null;
                            break;
                        }
                    }
                    if (!$bcvRate && isset($responseBCVList[0]['promedio'])) {
                        $bcvRate = $responseBCVList[0]['promedio'];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error consultando el Dólar BCV en comando: " . $e->getMessage());
        }

        if ($bcvRate && floatval($bcvRate) > 0) {
            ExchangeRate::updateOrCreate(
                ['currency_code' => 'BS'],
                ['rate' => floatval($bcvRate), 'source' => null]
            );
            Cache::forget('resources.exchange_rate.BS');
            $this->info("Dólar BCV actualizado: {$bcvRate} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Dólar BCV desde el API.");
        }

        // ── Dólar Binance → BINANCE ─────────────────────────────────────────
        $binanceRate = null;
        try {
            $responseBinance = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->post('https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search', [
                "fiat" => "VES",
                "page" => 1,
                "rows" => 5,
                "tradeType" => "BUY",
                "asset" => "USDT",
                "countries" => [],
                "proMerchantAds" => false,
                "shieldMerchantAds" => false,
                "publisherType" => null,
                "payTypes" => []
            ]);

            if ($responseBinance->successful() && isset($responseBinance->json('data')[0]['adv']['price'])) {
                $binanceRate = floatval($responseBinance->json('data')[0]['adv']['price']);
            }
        } catch (\Exception $e) {
            \Log::error("Error consultando Binance P2P en comando: " . $e->getMessage());
        }

        if ($binanceRate && floatval($binanceRate) > 0) {
            ExchangeRate::updateOrCreate(
                ['currency_code' => 'BINANCE'],
                ['rate' => floatval($binanceRate), 'source' => 'binance']
            );
            Cache::forget('resources.exchange_rate.BINANCE');
            $this->info("Dólar Binance actualizado: {$binanceRate} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Dólar Binance.");
        }

        // ── Euro → BS (ve.dolarapi.com) ──────────────────────────────────────
        $eurToVes = null;
        try {
            $responseEUR = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros/oficial');
            if ($responseEUR->successful() && !is_null($responseEUR->json('promedio'))) {
                $eurToVes = $responseEUR->json('promedio');
            } else {
                // Intento fallback con la lista general de euros
                $responseEURList = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros');
                if ($responseEURList->successful() && is_array($responseEURList->json())) {
                    foreach ($responseEURList->json() as $item) {
                        if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                            $eurToVes = $item['promedio'] ?? null;
                            break;
                        }
                    }
                    if (!$eurToVes && isset($responseEURList[0]['promedio'])) {
                        $eurToVes = $responseEURList[0]['promedio'];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error consultando el Euro en comando: " . $e->getMessage());
        }

        if ($eurToVes && floatval($eurToVes) > 0) {
            ExchangeRate::updateOrCreate(
                ['currency_code' => 'EUR'],
                ['rate' => floatval($eurToVes), 'source' => null]
            );
            Cache::forget('resources.exchange_rate.EUR');
            $this->info("Euro actualizado: {$eurToVes} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Euro desde el API.");
        }

        // Limpiar caché global de tasas de cambio
        Cache::forget('resources.all_exchange_rates');
    }
}

