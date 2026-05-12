<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateCreateRequest;
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
        return $this->exchangeRate->consultAll();
    }

    public function store(ExchangeRateCreateRequest $request)
    {
        $data = $request->data->all();

        // Si el rate viene vacío o es null, buscamos el valor de la API correspondiente
        if (!isset($data['rate']) || $data['rate'] === null || $data['rate'] === '') {
            $currency = $data['currency_code'];
            $rate = null;

            if ($currency === 'BS') {
                try {
                    $response = \Illuminate\Support\Facades\Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares/oficial');
                    $rate = $response->json('promedio');
                    if (!$rate) {
                        $responseList = \Illuminate\Support\Facades\Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/dolares');
                        if ($responseList->successful() && is_array($responseList->json())) {
                            foreach ($responseList->json() as $item) {
                                if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                                    $rate = $item['promedio'] ?? null;
                                    break;
                                }
                            }
                            if (!$rate && isset($responseList[0]['promedio'])) {
                                $rate = $responseList[0]['promedio'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Error consultando Dólar BCV en Controller: " . $e->getMessage());
                }
            } elseif ($currency === 'EUR') {
                try {
                    $response = \Illuminate\Support\Facades\Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros/oficial');
                    $rate = $response->json('promedio');
                    if (!$rate) {
                        $responseList = \Illuminate\Support\Facades\Http::retry(3, 1000)->get('https://ve.dolarapi.com/v1/euros');
                        if ($responseList->successful() && is_array($responseList->json())) {
                            foreach ($responseList->json() as $item) {
                                if (isset($item['fuente']) && $item['fuente'] === 'bcv') {
                                    $rate = $item['promedio'] ?? null;
                                    break;
                                }
                            }
                            if (!$rate && isset($responseList[0]['promedio'])) {
                                $rate = $responseList[0]['promedio'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Error consultando Euro en Controller: " . $e->getMessage());
                }
            }

            if ($rate && floatval($rate) > 0) {
                $data['rate'] = floatval($rate);
            } else {
                return response()->json("No se pudo obtener la tasa desde el API automáticamente.", 422);
            }
        }

        $this->exchangeRate->store($data);

        // Limpiar caché
        Cache::forget("resources.exchange_rate.{$data['currency_code']}");
        Cache::forget('resources.all_exchange_rates');

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

    public function consultOneEUR()
    {
        return $this->exchangeRate->consultOneEUR();
    }

    public function consultOneCOPC()
    {
        return $this->exchangeRate->consultOneCOPC();
    }

    public function updateBCVDollar(Request $request)
    {
        $response = Http::get('https://ve.dolarapi.com/v1/dolares');



        if ($request->exchange_id == null) {
            $data = [
                "currency_code" => "BS",
                "rate" => $response[0]['promedio'],
                "source" => null,
            ];
            $this->exchangeRate->store($data);

            // Limpiar caché
            Cache::forget("resources.exchange_rate.BS");
            Cache::forget('resources.all_exchange_rates');

            return response()->json($data);
        } else {
            $data = [
                "id" => $request->exchange_id,
                "currency_code" => "BS",
                "rate" => $response[0]['promedio'],
                "source" => null,
            ];

            $this->exchangeRate->updateBCVDollar($data);

            // Limpiar caché
            Cache::forget("resources.exchange_rate.BS");
            Cache::forget('resources.all_exchange_rates');

            return response()->json($data);

        }
    }
}
