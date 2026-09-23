<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSupplierRepository
{
    public function consultSupplierByProductWithBetterPrice($product_id, $conDescuento): Collection
    {
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');
        $minExpirationDate = now()->addMonths(6)->toDateString();

        // Obtener solo el ID más reciente por cada proveedor para este producto (máximo 30 días de antigüedad o actualizado y activo)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->where("product_id", "=", $product_id)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                  ->orWhere('updated_at', '>=', now()->subDays(30));
            })
            ->where(function ($q) use ($minExpirationDate) {
                $q->whereNull('expiration')
                  ->orWhere('expiration', '>', $minExpirationDate);
            });

        if ($hasIsActive) {
            $latestIdsQuery->where('is_active', true);
        }

        $latestIds = $latestIdsQuery->groupBy('supplier_id')->pluck('id');

        $consulta = ProductSupplier::query()
            ->whereIn("id", $latestIds)
            ->where(function ($q) use ($minExpirationDate) {
                $q->whereNull('expiration')
                  ->orWhere('expiration', '>', $minExpirationDate);
            });

        if ($conDescuento == "true") {
            $consulta->orderBy(DB::raw("CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END"), "ASC");
        } else {
            $consulta->orderBy(DB::raw("CASE WHEN unit_cost_usd > 0 THEN unit_cost_usd ELSE unit_cost_usd_with_discount END"), "ASC");
        }

        return $consulta->get();
    }

    public function consultarTodosLosProveedorProIdProducto($product_id): Collection
    {
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');

        // Obtener solo el ID más reciente por cada proveedor para este producto (máximo 30 días de antigüedad y activo)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->where("product_id", "=", $product_id)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                  ->orWhere('updated_at', '>=', now()->subDays(30));
            });

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
    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento, bool $skipAiMatch = false, ?int $supplierId = null): array
    {
        $productIds = $products->map(fn($p) => is_array($p) ? ($p['id'] ?? null) : ($p->id ?? null))->filter()->values()->toArray();
        $hasIsActive = \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'is_active');
        $minExpirationDate = now()->addMonths(6)->toDateString();
        
        // 1. Obtener solo los IDs más recientes por combinación de product_id y supplier_id (máximo 30 días y activo)
        // Descartando ofertas que venzan en los próximos 6 meses (si tienen dato de expiración)
        $latestIdsQuery = DB::table('product_suppliers')
            ->select(DB::raw('MAX(id) as id'))
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                  ->orWhere('updated_at', '>=', now()->subDays(30));
            })
            ->where(function ($q) use ($minExpirationDate) {
                $q->whereNull('expiration')
                  ->orWhere('expiration', '>', $minExpirationDate);
            });

        if ($supplierId) {
            $latestIdsQuery->where('supplier_id', $supplierId);
        }

        if ($hasIsActive) {
            $latestIdsQuery->where('is_active', true);
        }

        $latestIds = $latestIdsQuery->groupBy('product_id', 'supplier_id')->pluck('id');

        // 2. Obtener todas las ofertas disponibles para estos productos de una sola vez
        $query = ProductSupplier::with('supplier')
            ->whereIn('id', $latestIds)
            ->where(function ($q) use ($minExpirationDate) {
                $q->whereNull('expiration')
                  ->orWhere('expiration', '>', $minExpirationDate);
            });

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($hasIsActive) {
            $query->where('is_active', true);
        }

        $query->where(function ($query) {
            $query->where('unit_cost_usd', '>', 0)
                ->orWhere('unit_cost_usd_with_discount', '>', 0);
        });

        // 3. Ordenar por precio según preferencia del usuario (ignorando ceros)
        if ($conDescuento === "true") {
            $query->orderBy(DB::raw("CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END"), "ASC");
        } else {
            $query->orderBy(DB::raw("CASE WHEN unit_cost_usd > 0 THEN unit_cost_usd ELSE unit_cost_usd_with_discount END"), "ASC");
        }

        $allOffers = $query->get();

        // 4. Mapear de vuelta a la estructura que espera el servicio
        $results = [];
        
        // Mantener el orden de los productos entrantes
        foreach ($products as $product) {
            $productId = is_array($product) ? ($product['id'] ?? null) : ($product->id ?? null);
            $productBarcode = is_array($product) ? ($product['barcode'] ?? null) : ($product->barcode ?? null);
            $bestOffer = $allOffers->where('product_id', $productId)->first();

            // Si no tiene oferta asociada, intentar asociar por código de barras de manera automática y permanente (máximo 30 días)
            if (!$bestOffer && $productBarcode) {
                $barcodeQuery = ProductSupplier::where(function ($q) {
                        $q->where('created_at', '>=', now()->subDays(30))
                          ->orWhere('updated_at', '>=', now()->subDays(30));
                    })
                    ->where(function ($q) use ($minExpirationDate) {
                        $q->whereNull('expiration')
                          ->orWhere('expiration', '>', $minExpirationDate);
                    });

                if ($hasIsActive) {
                    $barcodeQuery->where('is_active', true);
                }

                $barcodeOffer = $barcodeQuery->with('supplier')
                    ->where(function ($q) use ($productBarcode) {
                        $q->where('barcode_match', $productBarcode)
                          ->orWhere('cod_supplier', $productBarcode);
                    })
                    ->where(function ($query) {
                        $query->where('unit_cost_usd', '>', 0)
                            ->orWhere('unit_cost_usd_with_discount', '>', 0);
                    })
                    ->orderBy(DB::raw("CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END"), "ASC")
                    ->first();

                if ($barcodeOffer && $productId) {
                    ProductSupplier::where('id', $barcodeOffer->id)->update([
                        'product_id' => $productId,
                        'is_ai_matched' => 0
                    ]);
                    $bestOffer = $barcodeOffer;
                }
            }

            // Si aún no tiene proveedor y NO se indicó omitir el match por IA: despachar Job asíncrono
            $noAiMatchPossible = is_array($product) ? ($product['no_ai_match_possible'] ?? false) : ($product->no_ai_match_possible ?? false);
            if (!$bestOffer && !$noAiMatchPossible && !$skipAiMatch && $productId) {
                \App\Jobs\MatchSupplierByIaJob::dispatch((int)$productId);
                // Marcar para que la UI sepa que está en proceso
                if (is_object($product)) {
                    $product->ia_matching_in_progress = true;
                }
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
