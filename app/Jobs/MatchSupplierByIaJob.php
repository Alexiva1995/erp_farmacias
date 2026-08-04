<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchSupplierByIaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Reintentos en caso de fallo de red con Gemini
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        protected int $productId
    ) {}

    public function handle(GeminiService $gemini): void
    {
        $product = Product::with('laboratory')->find($this->productId);

        if (!$product || $product->no_ai_match_possible) {
            return;
        }

        // Verificar si ya tiene un proveedor vinculado (puede haber cambiado desde el dispatch)
        $yaVinculado = ProductSupplier::where('product_id', $this->productId)
            ->where(function ($q) {
                $q->where('unit_cost_usd', '>', 0)
                  ->orWhere('unit_cost_usd_with_discount', '>', 0);
            })->exists();

        if ($yaVinculado) {
            return;
        }

        // IDs de product_suppliers ya rechazados para este producto (aprendizaje)
        $rechazadosIds = DB::table('supplier_ai_match_rejections')
            ->where('product_id', $this->productId)
            ->pluck('product_supplier_id')
            ->toArray();

        // Contar rechazos: si >= 3 registros, marcar como sin match posible
        if (count($rechazadosIds) >= 3) {
            $product->update(['no_ai_match_possible' => true]);
            return;
        }

        // Buscar candidatos con multi-estrategia
        $candidates = $this->buscarCandidatosMultiEstrategia($product, $rechazadosIds);

        if ($candidates->isEmpty()) {
            return;
        }

        // Obtener historial de rechazos detallado para aprendizaje en contexto
        $rejections = DB::table('supplier_ai_match_rejections')
            ->join('product_suppliers', 'supplier_ai_match_rejections.product_supplier_id', '=', 'product_suppliers.id')
            ->where('supplier_ai_match_rejections.product_id', $this->productId)
            ->select('product_suppliers.name as supplier_product_name', 'supplier_ai_match_rejections.reason')
            ->get()
            ->toArray();

        // Preparar datos para Gemini
        $productData = [
            'name'             => $product->name,
            'laboratory'       => $product->laboratory?->name ?? 'Genérico',
            'active_ingredient'=> $product->active_ingredient ?? '',
        ];

        $candidatesData = $candidates->map(fn($c) => [
            'id'               => $c->id,
            'name'             => $c->name,
            'laboratory'       => $c->laboratory ?? '',
            'active_ingredient'=> $c->active_ingredient ?? '',
        ])->values()->toArray();

        // Llamar a Gemini inyectando el historial de rechazos para in-context learning
        $aiResponse = $gemini->matchProduct($productData, $candidatesData, $rejections);

        if (!$aiResponse || empty($aiResponse['matched']) || empty($aiResponse['product_supplier_id'])) {
            return;
        }

        $supplierProductId = (int) $aiResponse['product_supplier_id'];
        $confidenceScore   = (float) ($aiResponse['confidence_score'] ?? 0);

        // Validar que el ID devuelto exista en los candidatos (previene alucinaciones)
        $matchedSupplier = $candidates->firstWhere('id', $supplierProductId);

        if (!$matchedSupplier) {
            Log::warning("[MatchSupplierByIaJob] Gemini devolvió ID {$supplierProductId} que no existe en candidatos para producto {$this->productId}.");
            return;
        }

        // Validación adicional: verificar concentración numérica si confianza < 0.9
        if ($confidenceScore < 0.9) {
            $concentracionValida = $this->validarConcentracion(
                $product->name,
                $matchedSupplier->name
            );

            if (!$concentracionValida) {
                return;
            }
        }

        // Persistir el match en BD
        ProductSupplier::where('id', $supplierProductId)->update([
            'product_id'    => $this->productId,
            'is_ai_matched' => 1,
        ]);

    }

    /**
     * Búsqueda multi-estrategia de candidatos del catálogo del proveedor.
     * SIEMPRE filtra whereNull('product_id') — productos ya vinculados no se analizan.
     * Orden: marca+ingrediente → solo ingrediente → tokens del nombre.
     */
    private function buscarCandidatosMultiEstrategia(Product $product, array $rechazadosIds): \Illuminate\Support\Collection
    {
        // Base: solo productos SIN vincular, con precio válido, excluyendo rechazados previos
        $base = ProductSupplier::whereNull('product_id')
            ->whereNotIn('id', $rechazadosIds)
            ->where(function ($q) {
                $q->where('unit_cost_usd', '>', 0)
                  ->orWhere('unit_cost_usd_with_discount', '>', 0);
            });

        $tieneIngrediente = !empty($product->active_ingredient) && strlen($product->active_ingredient) > 3;
        $tieneMarca       = !empty($product->laboratory?->name) && strlen($product->laboratory->name) > 2;

        // Estrategia 1: marca + ingrediente activo (coincidencia más específica)
        // Ejemplo: "BAYER" + "IBUPROFENO" no debe matchear con "IBUPROFENO GENÉRICO"
        if ($tieneIngrediente && $tieneMarca) {
            $candidatos = (clone $base)
                ->where('active_ingredient', 'like', '%' . $product->active_ingredient . '%')
                ->where('laboratory', 'like', '%' . $product->laboratory->name . '%')
                ->limit(15)
                ->get();

            if ($candidatos->isNotEmpty()) {
                return $candidatos;
            }
        }

        // Estrategia 2: solo ingrediente activo (sin filtrar por marca)
        // Cubre caso: mismo ingrediente, marca diferente en catálogo del proveedor
        if ($tieneIngrediente) {
            $candidatos = (clone $base)
                ->where('active_ingredient', 'like', '%' . $product->active_ingredient . '%')
                ->limit(15)
                ->get();

            if ($candidatos->isNotEmpty()) {
                return $candidatos;
            }
        }

        // Estrategia 3: tokens del nombre del producto (fallback)
        // Útil cuando active_ingredient está vacío o no coincide textualmente
        if (!empty($product->name)) {
            $stopWords = ['TAB', 'CAP', 'AMP', 'SOL', 'MG', 'ML', 'GR', 'X', 'DE', 'Y', 'CON'];
            $tokens    = array_filter(
                preg_split('/\s+/', strtoupper($product->name)),
                fn($t) => strlen($t) >= 4 && !in_array($t, $stopWords)
            );

            if (!empty($tokens)) {
                $query = (clone $base);
                foreach (array_slice($tokens, 0, 3) as $token) {
                    $query->where('name', 'like', "%{$token}%");
                }
                $candidatos = $query->limit(15)->get();

                if ($candidatos->isNotEmpty()) {
                    return $candidatos;
                }
            }
        }

        return collect();
    }


    /**
     * Valida que dos nombres de productos tengan la misma concentración numérica.
     * Extrae el primer número seguido de 'mg', 'ml', 'gr', 'mcg', 'ui' y los compara.
     */
    private function validarConcentracion(string $nombre1, string $nombre2): bool
    {
        $extractConcentracion = function (string $nombre): ?float {
            // Buscar patrón: número seguido de unidad (mg, ml, gr, mcg, ui, %)
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:mg|ml|gr|mcg|ui|%)/i', $nombre, $matches)) {
                return (float) str_replace(',', '.', $matches[1]);
            }
            return null;
        };

        $conc1 = $extractConcentracion($nombre1);
        $conc2 = $extractConcentracion($nombre2);

        // Si alguno no tiene concentración detectable, no podemos validar → permitir
        if ($conc1 === null || $conc2 === null) {
            return true;
        }

        // Tolerancia del 5% para manejar variaciones de redondeo (ej: 0.5mg vs 500mcg)
        return abs($conc1 - $conc2) <= ($conc1 * 0.05);
    }
}
