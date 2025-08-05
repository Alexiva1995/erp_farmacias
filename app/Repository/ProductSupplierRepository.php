<?php



namespace App\Repository;

use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\Collection;

class ProductSupplierRepository
{




    public function consultSupplierByProductWithBetterPrice($product_id): Collection
    {
        $consulta = ProductSupplier::query()
            ->where("product_id", "=", $product_id);

        return $consulta->orderBy("unit_cost", "ASC")->get();
    }
}
