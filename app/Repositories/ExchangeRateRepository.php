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
        $currencyCodes = ExchangeRate::query()
            ->distinct()
            ->pluck('currency_code');

        $latestRates = new Collection();
        foreach ($currencyCodes as $code) {
            $latest = ExchangeRate::query()
                ->where('currency_code', $code)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $latestRates->push($latest);
            }
        }

        return $latestRates;
    }

    public function consultOneCOP(): Model|null
    {
        return ExchangeRate::where('currency_code', 'COP')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneBCV(): Model|null
    {
        return ExchangeRate::where('currency_code', 'BS')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneBINANCE(): Model|null
    {
        return ExchangeRate::where('currency_code', 'BINANCE')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneEUR(): Model|null
    {
        return ExchangeRate::where('currency_code', 'EUR')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneCOPC(): Model|null
    {
        return ExchangeRate::where('currency_code', 'COPC')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneBsCOP(): Model|null
    {
        return ExchangeRate::where('currency_code', 'BS_COP')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function consultOneCOPS(): Model|null
    {
        return ExchangeRate::where('currency_code', 'COPS')->orderByDesc('updated_at')->orderByDesc('created_at')->orderByDesc('id')->first();
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

    public function fetchRateFromApi(string $currency): ?float
    {
        return null;
    }
}
