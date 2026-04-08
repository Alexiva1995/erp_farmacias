<?php

namespace App\Repository;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductSupplierRepository
{
    public function consultSupplierByProductWithBetterPrice($product_id, $conDescuento): Collection
    {
        $consulta = ProductSupplier::query()
            ->where("product_id", "=", $product_id);

        if ($conDescuento == "true") {
            $consulta->orderBy("unit_cost_usd_with_discount", "ASC");
        } else {
            $consulta->orderBy("unit_cost_usd", "ASC");
        }

        return $consulta->get();
    }

    public function consultarTodosLosProveedorProIdProducto($product_id): Collection
    {
        return ProductSupplier::where("product_id", "=", $product_id)
            ->with("supplier")
            ->get();
    }

    /**
     * Obtiene el mejor proveedor para una lista de productos de forma masiva (Optimizado)
     * Utilizado en la vista de Asistente de IA para comparativa de precios instantánea.
     */
    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento): array
    {
        $productIds = $products->pluck('id')->toArray();
        
        // 1. Obtener todas las ofertas disponibles para estos productos de una sola vez
        // No incluyas 'deleted_at' ya que la tabla product_suppliers no lo tiene.
        $query = DB::table('product_suppliers')
            ->whereIn('product_id', $productIds)
            ->where(function ($query) {
                $query->where('unit_cost_usd', '>', 0)
                    ->orWhere('unit_cost_usd_with_discount', '>', 0);
            });

        // 2. Ordenar por precio según preferencia del usuario
        if ($conDescuento === "true") {
            $query->orderBy("unit_cost_usd_with_discount", "ASC");
        } else {
            $query->orderBy("unit_cost_usd", "ASC");
        }

        $allOffers = $query->get();

        // 3. Mapear de vuelta a la estructura que espera el servicio y checkTolerance
        $results = [];
        
        // Importante: Mantener el orden de los productos entrantes
        foreach ($products as $product) {
            $bestOffer = $allOffers->where('product_id', $product->id)->first();
            
            // Si hay oferta, necesitamos el nombre del proveedor para la UI
            $supplier = null;
            if ($bestOffer) {
                // Caché simple o Lazy loading si es necesario, pero para 10-25 items está bien
                $supplier = Supplier::find($bestOffer->supplier_id);
            }

            $results[] = [
                'product' => $product,
                'supplier' => $supplier,
                'productSupplier' => $bestOffer,
                'precio_final_supplier' => 0, // Se hidratará en checkTolerance
                'percentageIncrease' => 0,
                'increase' => null,
                'tolerance' => 0,
            ];
        }

        return $results;
    }
}
