<?php

namespace App\Repositories;

use App\Contracts\Repositories\ZoomProductRepositoryInterface;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;

class ZoomProductRepository implements ZoomProductRepositoryInterface
{
    private const ZOOM_BARCODE = 'ZOOM-SERVICE';
    private const ZOOM_NAME = 'SERVICIO ZOOM';
    private const ZOOM_LOT_NUMBER = 'ZOOM-LOT';
    private const DEFAULT_STOCK = 999999;

    /**
     * Buscar el producto único de Zoom o crearlo si no existe en el catálogo.
     */
    public function findOrCreateZoomProduct(): Product
    {
        $product = Product::withoutGlobalScope('not_deleted')
            ->where(function ($query) {
                $query->where('barcode', self::ZOOM_BARCODE)
                    ->orWhere('name', self::ZOOM_NAME);
            })
            ->first();

        if (!$product) {
            $product = Product::create([
                'name' => self::ZOOM_NAME,
                'description' => 'Servicios de Envíos y Encomiendas Zoom',
                'active_ingredient' => 'ZOOM',
                'barcode' => self::ZOOM_BARCODE,
                'iva' => 1,
                'sale_price' => 0.00,
                'unit_cost' => 0.00,
                'is_deleted' => false,
            ]);
        } else {
            // Asegurar que no esté marcado como eliminado y mantenga el IVA activo
            if ($product->is_deleted || $product->iva != 1) {
                $product->is_deleted = false;
                $product->iva = 1;
                $product->save();
            }
        }

        $this->ensureStock($product);

        $product->loadSum('lots', 'quantity');
        $product->valid_stock_sum = (int) ($product->lots_sum_quantity ?? 0);

        return $product;
    }

    /**
     * Garantizar que el producto Zoom cuente con stock disponible en su lote.
     */
    public function ensureStock(Product $product): void
    {
        $currentStock = (int) $product->lots()->sum('quantity');

        if ($currentStock <= 100) {
            $lot = $product->lots()->where('lot_number', self::ZOOM_LOT_NUMBER)->first();

            if ($lot) {
                $lot->quantity = self::DEFAULT_STOCK;
                $lot->expiration_date = Carbon::now()->addYears(20)->toDateString();
                $lot->save();
            } else {
                ProductLot::create([
                    'product_id' => $product->id,
                    'lot_number' => self::ZOOM_LOT_NUMBER,
                    'quantity' => self::DEFAULT_STOCK,
                    'expiration_date' => Carbon::now()->addYears(20)->toDateString(),
                    'unit_cost' => 0.00,
                    'location' => 'PRINCIPAL',
                ]);
            }
        }
    }
}
