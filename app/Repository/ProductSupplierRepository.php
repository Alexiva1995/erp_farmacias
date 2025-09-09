<?php



namespace App\Repository;

use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\Collection;

class ProductSupplierRepository
{




    public function consultSupplierByProductWithBetterPrice($product_id, $conDescuento): Collection
    {
        $consulta = ProductSupplier::query()
            ->where("product_id", "=", $product_id);

        if ($conDescuento == "true") {
            $consulta->orderBy("unit_cost_usd", "ASC");
        } else {
            $consulta->orderBy("unit_cost_usd_with_discount", "ASC");
        }

        return $consulta->get();
    }
}
