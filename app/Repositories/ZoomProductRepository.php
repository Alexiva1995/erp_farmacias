<?php

namespace App\Repositories;

use App\Contracts\Repositories\ZoomProductRepositoryInterface;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;

class ZoomProductRepository implements ZoomProductRepositoryInterface
{
    private const ZOOM_BARCODE_TAXABLE = 'ZOOM-SERVICE';
    private const ZOOM_NAME_TAXABLE = 'SERVICIO ZOOM';
    private const ZOOM_LOT_NUMBER_TAXABLE = 'ZOOM-LOT';

    private const ZOOM_BARCODE_EXEMPT = 'ZOOM-SERVICE-EXEMPT';
    private const ZOOM_NAME_EXEMPT = 'SERVICIO ZOOM EXENTO';
    private const ZOOM_LOT_NUMBER_EXEMPT = 'ZOOM-LOT-EXEMPT';

    private const DEFAULT_STOCK = 999999;

    /**
     * Buscar el producto de Zoom (gravado G o exento) o crearlo si no existe en el catálogo.
     */
    public function findOrCreateZoomProduct(bool $isTaxable = true): Product
    {
        $barcode = $isTaxable ? self::ZOOM_BARCODE_TAXABLE : self::ZOOM_BARCODE_EXEMPT;
        $name = $isTaxable ? self::ZOOM_NAME_TAXABLE : self::ZOOM_NAME_EXEMPT;
        $iva = $isTaxable ? 1 : 0;
        $lotNumber = $isTaxable ? self::ZOOM_LOT_NUMBER_TAXABLE : self::ZOOM_LOT_NUMBER_EXEMPT;

        $product = Product::withoutGlobalScope('not_deleted')
            ->where(function ($query) use ($barcode, $name) {
                $query->where('barcode', $barcode)
                    ->orWhere('name', $name);
            })
            ->first();

        if (!$product) {
            $product = Product::create([
                'name' => $name,
                'description' => $isTaxable ? 'Servicios de Envíos y Encomiendas Zoom (Gravado)' : 'Servicios de Envíos y Encomiendas Zoom (Exento)',
                'active_ingredient' => 'ZOOM',
                'barcode' => $barcode,
                'iva' => $iva,
                'sale_price' => 0.00,
                'unit_cost' => 0.00,
                'is_deleted' => false,
            ]);
        } else {
            // Asegurar que no esté marcado como eliminado y mantenga el IVA correspondiente
            if ($product->is_deleted || (int) $product->iva !== $iva) {
                $product->is_deleted = false;
                $product->iva = $iva;
                $product->save();
            }
        }

        $this->ensureStock($product, $lotNumber);

        $product->loadSum('lots', 'quantity');
        $product->valid_stock_sum = (int) ($product->lots_sum_quantity ?? 0);

        return $product;
    }

    /**
     * Garantizar que el producto Zoom cuente con stock disponible en su lote.
     */
    public function ensureStock(Product $product, ?string $lotNumber = null): void
    {
        $targetLot = $lotNumber ?: ($product->barcode === self::ZOOM_BARCODE_EXEMPT ? self::ZOOM_LOT_NUMBER_EXEMPT : self::ZOOM_LOT_NUMBER_TAXABLE);
        $currentStock = (int) $product->lots()->sum('quantity');

        if ($currentStock <= 100) {
            $lot = $product->lots()->where('lot_number', $targetLot)->first();

            if ($lot) {
                $lot->quantity = self::DEFAULT_STOCK;
                $lot->expiration_date = Carbon::now()->addYears(20)->toDateString();
                $lot->save();
            } else {
                ProductLot::create([
                    'product_id' => $product->id,
                    'lot_number' => $targetLot,
                    'quantity' => self::DEFAULT_STOCK,
                    'expiration_date' => Carbon::now()->addYears(20)->toDateString(),
                    'unit_cost' => 0.00,
                    'location' => 'PRINCIPAL',
                ]);
            }
        }
    }
}
