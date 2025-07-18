<?php

namespace App\Repository;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateRepository
{

    public function consultAll(): Collection
    {
        $exhange = ExchangeRate::all();


        return $exhange;
    }

    public function consultOneCOP(): Model | null
    {
        $exhange = ExchangeRate::orderBy('created_at', 'desc')->where('currency_code', 'COP')->first();


        return $exhange;
    }

    public function store(array $data): Model
    {
        return ExchangeRate::create($data);
    }

    /*
    public function consultById(string $id): ?Model
    {
        $product = ProductProfitability::query()->where("product_id", "=", $id)->first();
        return $product;
    }


    

    public function editProduct(array $data): Model
    {
        ProductProfitability::where("id", "=", $data["id"])->update($data);
        return ProductProfitability::find($data["id"]);
    }

    //Debe haber un buscador de los prodcutos que ya tienen un dato guardado

    public function edit(array $data): Model
    {
        ProfitabilitySettings::where("id", "=", $data["id"])->update($data);
        return ProfitabilitySettings::find($data["id"]);
    }*/
}
