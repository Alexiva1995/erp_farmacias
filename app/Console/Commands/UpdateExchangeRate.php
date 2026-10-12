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
    public function handle(\App\Services\TelegramService $telegramService, \App\Services\ExchangeRateServices $exchangeRateService)
    {
        // ── Dólar BCV → BS (Portal BCV oficial / DolarApi) ───────────────────
        $bcvRate = $exchangeRateService->fetchRateFromApi('BS');

        if ($bcvRate && floatval($bcvRate) > 0) {
            ExchangeRate::create([
                'currency_code' => 'BS',
                'rate' => floatval($bcvRate),
                'source' => null,
            ]);
            Cache::forget('resources.exchange_rate.BS');
            $this->info("Dólar BCV actualizado: {$bcvRate} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Dólar BCV desde las fuentes oficiales.");
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
            ExchangeRate::create([
                'currency_code' => 'BINANCE',
                'rate' => floatval($binanceRate),
                'source' => 'binance',
            ]);
            Cache::forget('resources.exchange_rate.BINANCE');
            $this->info("Dólar Binance actualizado: {$binanceRate} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Dólar Binance.");
        }

        // ── Euro → BS (Portal BCV oficial / DolarApi) ───────────────────────
        $eurToVes = $exchangeRateService->fetchRateFromApi('EUR');

        if ($eurToVes && floatval($eurToVes) > 0) {
            ExchangeRate::create([
                'currency_code' => 'EUR',
                'rate' => floatval($eurToVes),
                'source' => null,
            ]);
            Cache::forget('resources.exchange_rate.EUR');
            $this->info("Euro actualizado: {$eurToVes} BS");
        } else {
            $this->error("No se pudo obtener la tasa de Euro desde el API.");
        }

        // Limpiar caché global de tasas de cambio
        Cache::forget('resources.all_exchange_rates');

        // Obtener tasas finales (las más recientes) de la base de datos para el reporte
        $bcvVal = ExchangeRate::where('currency_code', 'BS')->latest('id')->value('rate') ?? $bcvRate;
        $binanceVal = ExchangeRate::where('currency_code', 'BINANCE')->latest('id')->value('rate') ?? $binanceRate;
        $eurVal = ExchangeRate::where('currency_code', 'EUR')->latest('id')->value('rate') ?? $eurToVes;

        $bcvValFormatted = $bcvVal ? number_format((float) $bcvVal, 2, ',', '.') : 'No disponible';
        $binanceValFormatted = $binanceVal ? number_format((float) $binanceVal, 2, ',', '.') : 'No disponible';
        $eurValFormatted = $eurVal ? number_format((float) $eurVal, 2, ',', '.') : 'No disponible';

        $fechaStr = now()->format('d/m/Y h:i A');

        $message = "🔔 *Tasas cambiarias actualizadas automáticamente* 🔔\n\n" .
                   "💵 *Dólar BCV:* {$bcvValFormatted} Bs.\n" .
                   "🟢 *Dólar Binance:* {$binanceValFormatted} Bs.\n" .
                   "💶 *Euro Oficial:* {$eurValFormatted} Bs.\n\n" .
                   "Valores actualizados al *{$fechaStr}*.";

        if ($telegramService->isCommandActive('/tasas', 'generales')) {
            $targetChatId = $telegramService->getTargetChatIdForCommand('/tasas', 'generales');
            $telegramService->sendMessage($message, $targetChatId);
        }
    }
}

