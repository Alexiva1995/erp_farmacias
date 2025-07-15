<?php

namespace App\Http\Controllers\api;

use App\Contracts\ExchangeRate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{

    public function __construct(
        protected ExchangeRate $exchangeRate
    ) {}

    public function consultAll(Request $request)
    {
        return $this->exchangeRate->consultAll();
    }

    public function store(Request $request)
    {
        $crear = [
            'currency_code' => $request->currency_code,
            'rate'          => $request->rate,
            'source'        => $request->source
        ];
        $this->exchangeRate->store($crear);
        return response()->json("store funcionando");
    }
}
