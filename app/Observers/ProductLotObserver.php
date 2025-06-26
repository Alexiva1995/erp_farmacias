<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductLot;

class ProductLotObserver
{
    /**
     * Handle the ProductLot "created" event.
     *
     * @param  \App\Models\ProductLot  $productLot
     * @return void
     */
    public function created(ProductLot $productLot)
    {
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "updated" event.
     *
     * @param  \App\Models\ProductLot  $productLot
     * @return void
     */
    public function updated(ProductLot $productLot)
    {

        if ($productLot->isDirty('quantity')) {
            $this->updateProductStock($productLot->product);
        }
    }

    /**
     * Handle the ProductLot "deleted" event.
     *
     * @param  \App\Models\ProductLot  $productLot
     * @return void
     */
    public function deleted(ProductLot $productLot)
    {
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "restored" event.
     *
     * @param  \App\Models\ProductLot  $productLot
     * @return void
     */
    public function restored(ProductLot $productLot)
    {
        $this->updateProductStock($productLot->product);
    }

    /**
     * Handle the ProductLot "force deleted" event.
     *
     * @param  \App\Models\ProductLot  $productLot
     * @return void
     */
    public function forceDeleted(ProductLot $productLot)
    {
        $this->updateProductStock($productLot->product);
    }

    /**
     * Recalcula y actualiza el stock total del producto asociado.
     *
     * @param Product $product El producto cuyo stock necesita ser actualizado.
     */
    protected function updateProductStock(Product $product)
    {
        $totalStock = $product->lots()->sum('quantity');
        $product->updateQuietly(['stock' => $totalStock]);
    }
}
