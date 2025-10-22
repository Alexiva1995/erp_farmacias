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
    ) {}

    public function consultAll(Request $request)
    {
        return $this->exchangeRate->consultAll();
    }

    public function store(ExchangeRateCreateRequest $request)
    {
        //dd($request->data);
        $this->exchangeRate->store($request->data->all());
        return response()->json("store funcionando");
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

    public function updateBCVDollar(Request $request)
    {
        $response = Http::get('https://ve.dolarapi.com/v1/dolares');

        $data = [
            "currency_code" => "USD",
            "rate"          => $response[0]['promedio'],
            "source"        => null,
        ];

        $this->exchangeRate->store($data);
        return response()->json("Dolar BCV actualizado");
    }
}
