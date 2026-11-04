<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateCreateRequest;
use App\Http\Resources\Finances\ExchangeRateResource;
use App\Services\Resources\ResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ExchangeRateController extends Controller
{

    public function __construct(
        protected ExchangeRate $exchangeRate
    ) {
    }

    public function consultAll(Request $request)
    {
        $rates = $this->exchangeRate->consultAll();
        return ExchangeRateResource::collection($rates);
    }

    public function store(ExchangeRateCreateRequest $request)
    {
        $data = $request->data->all();

        try {
            $this->exchangeRate->store($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json($e->getMessage(), 422);
        }

        // Limpiar caché
        Cache::forget(ResourceService::exchangeRateCacheKey($data['currency_code']));
        Cache::forget(ResourceService::allExchangeRatesCacheKey());

        return response()->json("Tasa de cambio procesada");
    }

    public function apiDollar()
    {
        $response = Http::get('https://ve.dolarapi.com/v1/dolares');

        return $response->json();
    }

    public function consultOneCOP()
    {
        return $this->exchangeRate->consultOneCOP();
    }

    public function consultOneBCV()
    {
        return $this->exchangeRate->consultOneBCV();
    }

    public function consultOneBINANCE()
    {
        return $this->exchangeRate->consultOneBINANCE();
    }

    public function consultOneEUR()
    {
        return $this->exchangeRate->consultOneEUR();
    }

    public function consultOneCOPC()
    {
        return $this->exchangeRate->consultOneCOPC();
    }

    public function consultOneBsCOP()
    {
        return $this->exchangeRate->consultOneBsCOP();
    }

    public function consultOneCOPS()
    {
        return $this->exchangeRate->consultOneCOPS();
    }

    public function updateBCVDollar(Request $request)
    {
        $rate = $this->exchangeRate->fetchRateFromApi('BS') ?? 1.0;

        if ($request->exchange_id == null) {
            $data = [
                "currency_code" => "BS",
                "rate" => $rate,
                "source" => null,
            ];
            $this->exchangeRate->store($data);

            // Limpiar caché
            Cache::forget(ResourceService::exchangeRateCacheKey('BS'));
            Cache::forget(ResourceService::allExchangeRatesCacheKey());

            return response()->json($data);
        } else {
            $data = [
                "id" => $request->exchange_id,
                "currency_code" => "BS",
                "rate" => $rate,
                "source" => null,
            ];

            $this->exchangeRate->updateBCVDollar($data);

            // Limpiar caché
            Cache::forget(ResourceService::exchangeRateCacheKey('BS'));
            Cache::forget(ResourceService::allExchangeRatesCacheKey());

            return response()->json($data);
        }
    }
}
