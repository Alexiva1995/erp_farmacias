<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ExchangeRate;
use App\Repositories\ExchangeRateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateServices implements ExchangeRate
{
    public function __construct(protected ExchangeRateRepository $exchangeRateRepository)
    {
    }

    public function consultAll(): Collection
    {
        return $this->exchangeRateRepository->consultAll();
    }

    public function store(array $data): Model
    {
        if (!isset($data['rate']) || $data['rate'] === null || $data['rate'] === '') {
            $fetchedRate = $this->fetchRateFromApi($data['currency_code']);
            if ($fetchedRate && floatval($fetchedRate) > 0) {
                $data['rate'] = floatval($fetchedRate);
            } else {
                throw new \InvalidArgumentException("No se pudo obtener la tasa desde la API automáticamente para " . $data['currency_code']);
            }
        }

        $model = $this->exchangeRateRepository->store($data);

        // Limpiar caché de tasas de cambio
        \Illuminate\Support\Facades\Cache::forget("resources.exchange_rate.{$data['currency_code']}");
        \Illuminate\Support\Facades\Cache::forget('resources.all_exchange_rates');

        return $model;
    }

    /**
     * Consulta la tasa oficial directamente desde el portal web del BCV (bcv.org.ve).
     * Soporta 'BS' (Dólar) y 'EUR' (Euro).
     */
    public function fetchDirectBcvRate(string $currency = 'BS'): ?float
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])
                ->get('https://www.bcv.org.ve/');

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $targetId = ($currency === 'EUR') ? 'euro' : 'dolar';

            if (preg_match("/<div id=\"{$targetId}\"[\s\S]*?<strong[^>]*class=\"strong-tb\"[^>]*>\s*([0-9.,]+)\s*<\/strong>/is", $html, $matches)) {
                $raw = str_replace(['.', ' '], '', $matches[1]);
                $clean = str_replace(',', '.', $raw);
                $rate = (float) $clean;

                return $rate > 0 ? $rate : null;
            }
        } catch (\Exception $e) {
            Log::warning("Error consultando portal oficial BCV directamente para {$currency}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Consulta la tasa a través de la API externa (dolarapi.com).
     */
    public function fetchDolarApiRate(string $currency = 'BS'): ?float
    {
        try {
            $isEur = ($currency === 'EUR');
            $endpoint = $isEur ? 'https://ve.dolarapi.com/v1/euros/oficial' : 'https://ve.dolarapi.com/v1/dolares/oficial';
            $fallbackEndpoint = $isEur ? 'https://ve.dolarapi.com/v1/euros' : 'https://ve.dolarapi.com/v1/dolares';

            $response = Http::retry(2, 500)->timeout(6)->get($endpoint);
            $rate = $response->json('promedio');

            if (!$rate) {
                $responseList = Http::retry(2, 500)->timeout(6)->get($fallbackEndpoint);
                if ($responseList->successful() && is_array($responseList->json())) {
                    foreach ($responseList->json() as $item) {
                        if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                            return (float) ($item['promedio'] ?? null);
                        }
                    }
                    if (isset($responseList[0]['promedio'])) {
                        return (float) $responseList[0]['promedio'];
                    }
                }
            }

            return $rate ? (float) $rate : null;
        } catch (\Exception $e) {
            Log::error("Error consultando DolarApi para {$currency}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la tasa del BCV con DolarApi como principal y portal BCV como respaldo:
     * - DolarApi es la fuente principal.
     * - Si el portal oficial del BCV tiene un valor mayor (ej. fines de semana o feriados con tasa del día posterior),
     *   se usa la del BCV.
     * - Si DolarApi falla o no responde, se usa el portal del BCV como respaldo.
     */
    public function fetchOptimalBcvRate(string $currency = 'BS'): ?float
    {
        // 1. Fuente principal: DolarApi
        $dolarApiRate = $this->fetchDolarApiRate($currency);

        // 2. Consulta de verificación/respaldo en el portal del BCV
        $directBcvRate = $this->fetchDirectBcvRate($currency);

        if ($dolarApiRate !== null && $directBcvRate !== null) {
            // Si el portal del BCV tiene una tasa mayor (ej. fin de semana o festivo), se usa la del BCV
            if ($directBcvRate > $dolarApiRate) {
                return $directBcvRate;
            }

            // En caso contrario, prevalece la principal (DolarApi)
            return $dolarApiRate;
        }

        // Si DolarApi falló, se toma el respaldo del BCV, y viceversa
        return $dolarApiRate ?? $directBcvRate;
    }

    public function fetchRateFromApi(string $currency): ?float
    {
        if ($currency === 'BS') {
            return $this->fetchOptimalBcvRate('BS');
        }

        if ($currency === 'EUR') {
            return $this->fetchOptimalBcvRate('EUR');
        }

        if ($currency === 'BINANCE') {
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
                    return floatval($responseBinance->json('data')[0]['adv']['price']);
                }
            } catch (\Exception $e) {
                Log::error("Error consultando Binance P2P en ExchangeRateServices: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    public function consultOneCOP(): Model|null
    {
        return $this->exchangeRateRepository->consultOneCOP();
    }

    public function consultOneBCV(): Model|null
    {
        return $this->exchangeRateRepository->consultOneBCV();
    }

    public function consultOneBINANCE(): Model|null
    {
        return $this->exchangeRateRepository->consultOneBINANCE();
    }

    public function consultOneEUR(): Model|null
    {
        return $this->exchangeRateRepository->consultOneEUR();
    }

    public function consultOneCOPC(): Model|null
    {
        return $this->exchangeRateRepository->consultOneCOPC();
    }

    public function consultOneBsCOP(): Model|null
    {
        return $this->exchangeRateRepository->consultOneBsCOP();
    }

    public function consultOneCOPS(): Model|null
    {
        return $this->exchangeRateRepository->consultOneCOPS();
    }

    public function updateBCVDollar(array $data): Model
    {
        $model = $this->exchangeRateRepository->updateBCVDollar($data);

        // Limpiar caché de tasas de cambio
        \Illuminate\Support\Facades\Cache::forget("resources.exchange_rate.BS");
        \Illuminate\Support\Facades\Cache::forget('resources.all_exchange_rates');

        return $model;
    }
}
