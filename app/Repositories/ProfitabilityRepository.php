<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProfitabilitySettings;
use App\Models\ProductProfitability;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProfitabilityRepository implements \App\Contracts\Profitability
{

    public function consultOne(): Model | null
    {
        $product = ProfitabilitySettings::orderBy('created_at', 'desc')->first();


        return $product;
    }

    public function consultById(string $id): ?Model
    {
        $product = ProductProfitability::query()->where("product_id", "=", $id)->first();
        return $product;
    }

    public function store(array $data): Model
    {
        return ProfitabilitySettings::create($data);
    }

    public function storeProduct(array $data): Model
    {
        return ProductProfitability::create($data);
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
    }
}
