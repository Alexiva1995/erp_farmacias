<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductSupplierRepository
{
    public function consultSupplierByProductWithBetterPrice($product_id, $conDescuento): Collection
    {
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');

        // Obtener solo el ID más reciente por cada proveedor para este producto (máximo 7 días de antigüedad y activo)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->where("product_id", "=", $product_id)
            ->where('created_at', '>=', now()->subDays(7));

        if ($hasIsActive) {
            $latestIdsQuery->where('is_active', true);
        }

        $latestIds = $latestIdsQuery->groupBy('supplier_id')->pluck('id');

        $consulta = ProductSupplier::query()
            ->whereIn("id", $latestIds);

        if ($conDescuento == "true") {
            $consulta->where("unit_cost_usd_with_discount", ">", 0)
                ->orderBy("unit_cost_usd_with_discount", "ASC");
        } else {
            $consulta->where("unit_cost_usd", ">", 0)
                ->orderBy("unit_cost_usd", "ASC");
        }

        return $consulta->get();
    }

    public function consultarTodosLosProveedorProIdProducto($product_id): Collection
    {
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');

        // Obtener solo el ID más reciente por cada proveedor para este producto (máximo 7 días de antigüedad y activo)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->where("product_id", "=", $product_id)
            ->where('created_at', '>=', now()->subDays(7));

        if ($hasIsActive) {
            $latestIdsQuery->where('is_active', true);
        }

        $latestIds = $latestIdsQuery->groupBy('supplier_id')->pluck('id');

        return ProductSupplier::whereIn("id", $latestIds)
            ->with("supplier")
            ->get();
    }

    /**
     * Obtiene el mejor proveedor para una lista de productos de forma masiva (Optimizado)
     * Utilizado en la vista de Asistente de IA para comparativa de precios instantánea.
     */
    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento, bool $skipAiMatch = false): array
    {
        $productIds = $products->pluck('id')->toArray();
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');
        
        // 1. Obtener solo los IDs más recientes por combinación de product_id y supplier_id (máximo 7 días y activo)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->whereIn('product_id', $productIds)
            ->where('created_at', '>=', now()->subDays(7));

        if ($hasIsActive) {
            $latestIdsQuery->where('is_active', true);
        }

        $latestIds = $latestIdsQuery->groupBy('product_id', 'supplier_id')->pluck('id');

        // 2. Obtener todas las ofertas disponibles para estos productos de una sola vez
        $query = ProductSupplier::with('supplier')
            ->whereIn('id', $latestIds);

        if ($hasIsActive) {
            $query->where('is_active', true);
        }

        $query->where(function ($query) {
            $query->where('unit_cost_usd', '>', 0)
                ->orWhere('unit_cost_usd_with_discount', '>', 0);
        });

        // 3. Ordenar por precio según preferencia del usuario (ignorando ceros)
        if ($conDescuento === "true") {
            $query->where("unit_cost_usd_with_discount", ">", 0)
                ->orderBy("unit_cost_usd_with_discount", "ASC");
        } else {
            $query->where("unit_cost_usd", ">", 0)
                ->orderBy("unit_cost_usd", "ASC");
        }

        $allOffers = $query->get();

        // 4. Mapear de vuelta a la estructura que espera el servicio
        $results = [];
        
        // Mantener el orden de los productos entrantes
        foreach ($products as $product) {
            $bestOffer = $allOffers->where('product_id', $product->id)->first();

            // Si no tiene oferta asociada, intentar asociar por código de barras de manera automática y permanente (máximo 7 días)
            if (!$bestOffer && $product->barcode) {
                $barcodeQuery = ProductSupplier::where('created_at', '>=', now()->subDays(7));

                if ($hasIsActive) {
                    $barcodeQuery->where('is_active', true);
                }

                $barcodeOffer = $barcodeQuery->where(function ($q) use ($product) {
                        $q->where('barcode_match', $product->barcode)
                          ->orWhere('cod_supplier', $product->barcode);
                    })
                    ->first();
                if ($barcodeOffer) {
                    ProductSupplier::where('id', $barcodeOffer->id)->update([
                        'product_id' => $product->id,
                        'is_ai_matched' => 0
                    ]);
                    $bestOffer = $barcodeOffer;
                }
            }

            // Si aún no tiene proveedor y NO se indicó omitir el match por IA: despachar Job asíncrono
            if (!$bestOffer && !$product->no_ai_match_possible && !$skipAiMatch) {
                \App\Jobs\MatchSupplierByIaJob::dispatch($product->id);
                // Marcar para que la UI sepa que está en proceso
                $product->ia_matching_in_progress = true;
            }
            
            $results[] = [
                'product'              => $product,
                'supplier'             => $bestOffer ? $bestOffer->supplier : null,
                'productSupplier'      => $bestOffer,
                'precio_final_supplier'=> 0,
                'percentageIncrease'   => 0,
                'increase'             => null,
                'tolerance'            => 0,
            ];
        }

        return $results;
    }
}
