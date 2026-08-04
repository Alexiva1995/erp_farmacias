<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateRepository implements \App\Contracts\ExchangeRate
{

    public function consultAll(): Collection
    {
        return ExchangeRate::query()
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('exchange_rates')
                    ->groupBy('currency_code');
            })
            ->get();
    }

    public function consultOneCOP(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'COP')->first();
    }

    public function consultOneBCV(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'BS')->first();
    }

    public function consultOneBINANCE(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'BINANCE')->first();
    }

    public function consultOneEUR(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'EUR')->first();
    }

    public function consultOneCOPC(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'COPC')->first();
    }

    public function consultOneBsCOP(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'BS_COP')->first();
    }

    public function consultOneCOPS(): Model|null
    {
        return ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'COPS')->first();
    }

    public function updateBCVDollar(array $data): Model
    {
        ExchangeRate::where("id", "=", $data["id"])->update($data);
        return ExchangeRate::find($data["id"]);
    }

    public function store(array $data): Model
    {
        return ExchangeRate::create($data);
    }
}
