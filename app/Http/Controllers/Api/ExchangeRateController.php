<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ExchangeRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
                $response = \Illuminate\Support\Facades\Http::get('https://ve.dolarapi.com/v1/dolares/oficial');
                $rate = $response->json('promedio');
            } elseif ($currency === 'EUR') {
                $response = \Illuminate\Support\Facades\Http::get('https://ve.dolarapi.com/v1/euros/oficial');
                $rate = $response->json('promedio');
            }

            if ($rate) {
                $data['rate'] = $rate;
            } else {
                return response()->json("No se pudo obtener la tasa desde el API automáticamente.", 422);
            }
        }

        $this->exchangeRate->store($data);
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
            return response()->json($data);
        } else {
            $data = [
                "id" => $request->exchange_id,
                "currency_code" => "BS",
                "rate" => $response[0]['promedio'],
                "source" => null,
            ];

            $this->exchangeRate->updateBCVDollar($data);
            return response()->json($data);

        }
    }
}
