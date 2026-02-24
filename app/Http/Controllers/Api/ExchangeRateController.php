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
        // puede ser que se requiera hacer lo mismo como el valor del dolar
        if ($request->data->id == null) {

            $this->exchangeRate->store($request->data->all());
            return response()->json("Se ha creado la tasa de cambio COP");
        } else {
            $this->exchangeRate->updateBCVDollar($request->data->all());
            return response()->json("Tasa de cambio actualizado");
        }


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
