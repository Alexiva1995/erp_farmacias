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

        return $this->exchangeRateRepository->store($data);
    }

    public function fetchRateFromApi(string $currency): ?float
    {
        if ($currency === 'BS') {
            try {
                $response = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares/oficial');
                $rate = $response->json('promedio');
                if (!$rate) {
                    $responseList = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares');
                    if ($responseList->successful() && is_array($responseList->json())) {
                        foreach ($responseList->json() as $item) {
                            if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                                return (float)($item['promedio'] ?? null);
                            }
                        }
                        if (isset($responseList[0]['promedio'])) {
                            return (float)$responseList[0]['promedio'];
                        }
                    }
                }
                return $rate ? (float)$rate : null;
            } catch (\Exception $e) {
                Log::error("Error consultando Dólar BCV en ExchangeRateServices: " . $e->getMessage());
                return null;
            }
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

        if ($currency === 'EUR') {
            try {
                $response = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros/oficial');
                $rate = $response->json('promedio');
                if (!$rate) {
                    $responseList = Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros');
                    if ($responseList->successful() && is_array($responseList->json())) {
                        foreach ($responseList->json() as $item) {
                            if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                                return (float)($item['promedio'] ?? null);
                            }
                        }
                        if (isset($responseList[0]['promedio'])) {
                            return (float)$responseList[0]['promedio'];
                        }
                    }
                }
                return $rate ? (float)$rate : null;
            } catch (\Exception $e) {
                Log::error("Error consultando Euro en ExchangeRateServices: " . $e->getMessage());
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
        return $this->exchangeRateRepository->updateBCVDollar($data);
    }
}
