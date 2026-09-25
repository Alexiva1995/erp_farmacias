<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\AutoReplenishmentConfig;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Enums\AutoOrderStatus;
use App\Services\Reports\IaAssistantReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoReplenishmentCommand extends Command
{
    protected $signature   = 'replenishment:run {--config= : ID específico de config a ejecutar}';
    protected $description = 'Genera órdenes de compra automáticamente según las configuraciones activas';

    public function __construct(
        protected IaAssistantReportService $reportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $configId = $this->option('config');

        $query = AutoReplenishmentConfig::with('supplier');
        if ($configId) {
            $query->where('id', $configId);
        } else {
            $query->where('is_active', true);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->warn('No hay configuraciones activas de reposición automática.');
            return self::SUCCESS;
        }

        $hasErrors = false;
        foreach ($configs as $config) {
            $ok = $this->procesarConfig($config);
            if (!$ok) {
                $hasErrors = true;
            }
        }

        if ($hasErrors && $configId) {
            return self::FAILURE;
        }

        $this->info('✅ Reposición automática completada.');
        return self::SUCCESS;
    }

    private function procesarConfig(AutoReplenishmentConfig $config): bool
    {
        $this->line("  → Procesando: [{$config->id}] {$config->name}");

        try {
            // 1. Construir los $filtros compatibles con IaAssistantReportService
            $filtros = $this->construirFiltros($config);

            // 2. Obtener productos en falla usando el MISMO motor que usa la UI
            $productos = $this->reportService->getFilteredReportWithoutPaginate($filtros);

            if ($productos->isEmpty()) {
                $this->line("     Sin productos que reponer para: {$config->name}");
                $config->update(['last_run_at' => now(), 'last_run_products' => 0, 'last_run_orders' => 0]);
                return true;
            }

            // 3. Filtrar: solo los que tienen proveedor vinculado y solicitar >= min_solicitar
            $aReponer = $productos->filter(function ($item) use ($config) {
                $solicitar = (float) ($item->solicitar ?? 0);
                $tieneProveedor = $item->best_supplier !== null;
                return $tieneProveedor && $solicitar >= $config->min_solicitar;
            });

            if ($aReponer->isEmpty()) {
                $this->line("     Sin productos con proveedor y solicitar ≥ {$config->min_solicitar}.");
                $config->update(['last_run_at' => now(), 'last_run_products' => 0, 'last_run_orders' => 0]);
                return true;
            }

            $this->line("     Productos a reponer: {$aReponer->count()}");

            // 4. Crear las órdenes de compra en una transacción
            $ordenesCreadas = DB::transaction(function () use ($aReponer, $config) {
                return $this->crearOrdenes($aReponer, $config);
            });

            $config->update([
                'last_run_at'       => now(),
                'last_run_products' => $aReponer->count(),
                'last_run_orders'   => count($ordenesCreadas),
            ]);

            $this->info("     ✅ {$aReponer->count()} productos → " . count($ordenesCreadas) . " órdenes creadas/actualizadas.");
            return true;

        } catch (\Throwable $e) {
            $this->error("     ❌ Error en '{$config->name}': " . $e->getMessage());
            Log::error("[AutoReplenishment] Error en config {$config->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Construye el array $filtros compatible con IaAssistantReportService::getFilteredReportWithoutPaginate()
     */
    private function construirFiltros(AutoReplenishmentConfig $config): array
    {
        $filtros = [
            'tipo_filtracion' => $config->tipo_filtracion,
            'lapso_de_tiempo' => $config->lapso_de_tiempo,
            'stock'           => $config->stock_filter,     // 'fallas' por defecto
            'con_descuento'   => $config->con_descuento,
            'with_suppliers'  => true,
            'show_ignored'    => $config->include_ignored ?? true,
            'orderBy'         => 'desc',
            'sortBy'          => 'solicitar',
            'tipo_vista'      => false,                     // Vista individual (no grupal)
        ];

        // Si tiene proveedor preferido, filtrar solo ese
        if ($config->supplier_id) {
            $filtros['supplier_id'] = $config->supplier_id;
        }

        // Exclusión de productos Plan Colombia y Novaventa
        if ($config->exclude_colombian) {
            $filtros['isColombian'] = false;
        }

        if ($config->exclude_novaventa) {
            $filtros['isNovaventa'] = false;
        }

        // Si tiene grupos específicos, filtrarlos
        if (!empty($config->group_ids)) {
            $filtros['groups'] = $config->group_ids;
        }

        return $filtros;
    }

    /**
     * Crea o actualiza AutoOrders y AutoOrderDetails para cada producto a reponer.
     * Evalúa los mínimos de compra por proveedor: si no se alcanza el mínimo (y no es Drotaca),
     * reasigna el producto a un proveedor alternativo; si no hay otro proveedor, mantiene la orden.
     */
    private function crearOrdenes($aReponer, AutoReplenishmentConfig $config): array
    {
        $conDescuento = (bool) $config->con_descuento;
        $itemsFiltrados = [];

        // 1. Filtrar productos no bloqueados por vencimiento próximo (< 120 días)
        foreach ($aReponer as $item) {
            $product = $item->product ?? $item;

            $tieneBloqueo = DB::table('product_lots')
                ->where('product_id', $product->id)
                ->where('quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', now()->addDays(120)->toDateString())
                ->exists();

            if ($tieneBloqueo) {
                $this->warn("     ⏩ [{$product->id}] {$product->name} — Bloqueado por vencimiento < 120 días. Se omite.");
                Log::info("[AutoReplenishment] Producto ID {$product->id} omitido por bloqueo de vencimiento.", [
                    'product_name' => $product->name,
                    'config_id'    => $config->id,
                ]);
                continue;
            }

            $itemsFiltrados[] = $item;
        }

        if (empty($itemsFiltrados)) {
            return [];
        }

        $productIds = collect($itemsFiltrados)->map(fn($it) => ($it->product ?? $it)->id)->unique()->values()->toArray();
        $minExpirationDate = now()->addMonths(6)->toDateString();

        // 2. Obtener todas las ofertas activas vigentes de proveedores para estos productos en una sola consulta
        $offersQuery = ProductSupplier::with('supplier')
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                  ->orWhere('updated_at', '>=', now()->subDays(30));
            })
            ->where(function ($q) use ($minExpirationDate) {
                $q->whereNull('expiration')
                  ->orWhere('expiration', '>', $minExpirationDate);
            })
            ->where(function ($q) {
                $q->where('unit_cost_usd', '>', 0)
                  ->orWhere('unit_cost_usd_with_discount', '>', 0);
            });

        if ($config->supplier_id) {
            $offersQuery->where('supplier_id', $config->supplier_id);
        }

        $allOffersGrouped = $offersQuery->get()->groupBy('product_id');

        // 3. Mapear para cada producto todas las opciones de proveedor disponibles ordenadas por mejor costo
        $productAssignments = []; // productId => [ 'item', 'product', 'quantity', 'selected_offer_index', 'offers' => [...] ]

        foreach ($itemsFiltrados as $item) {
            $product = $item->product ?? $item;
            $quantity = (float) ($item->solicitar ?? $product->solicitar ?? 1);
            $quantity = max(1, ceil($quantity));

            $offers = $allOffersGrouped->get($product->id, collect());

            // Ordenar ofertas por precio (con o sin descuento según la configuración)
            $sortedOffers = $offers->sortBy(function ($ps) use ($conDescuento) {
                if ($conDescuento && $ps->unit_cost_usd_with_discount > 0) {
                    return (float) $ps->unit_cost_usd_with_discount;
                }
                return (float) ($ps->unit_cost_usd > 0 ? $ps->unit_cost_usd : ($ps->unit_cost_usd_with_discount ?? 999999));
            })->values();

            if ($sortedOffers->isEmpty()) {
                // Si no vino de la BD de ofertas pero vino hidratado con best_supplier
                if ($item->best_supplier) {
                    $unitCost = (float) ($item->best_supplier_price ?? $product->unit_cost ?? 0);
                    $productAssignments[$product->id] = [
                        'item'                 => $item,
                        'product'              => $product,
                        'quantity'             => $quantity,
                        'selected_offer_index' => 0,
                        'offers'               => [
                            [
                                'supplier'        => $item->best_supplier,
                                'productSupplier' => $item->product_supplier ?? null,
                                'unit_cost'       => $unitCost,
                            ],
                        ],
                    ];
                }
                continue;
            }

            $formattedOffers = [];
            $maxPct = $config->max_price_increase_percentage !== null ? (float) $config->max_price_increase_percentage : null;
            $baseCost = (float) ($product->unit_cost ?? 0);

            foreach ($sortedOffers as $ps) {
                $cost = $conDescuento && $ps->unit_cost_usd_with_discount > 0
                    ? (float) $ps->unit_cost_usd_with_discount
                    : (float) ($ps->unit_cost_usd > 0 ? $ps->unit_cost_usd : ($product->unit_cost ?? 0));

                // Validación de sobrecosto máximo si está parametrizado
                if ($maxPct !== null && $baseCost > 0) {
                    $increasePct = (($cost - $baseCost) / $baseCost) * 100;
                    if ($increasePct > $maxPct) {
                        $this->warn("     ⚠️ [{$product->id}] {$product->name} en proveedor '{$ps->supplier?->name}' tiene sobrecosto de +".round($increasePct, 1)."% (supera el límite de +{$maxPct}%). Se descarta esta oferta.");
                        continue;
                    }
                }

                $formattedOffers[] = [
                    'supplier'        => $ps->supplier,
                    'productSupplier' => $ps,
                    'unit_cost'       => $cost,
                ];
            }

            if (empty($formattedOffers)) {
                $this->warn("     ⏩ [{$product->id}] {$product->name} — Ninguna oferta cumple con el límite de aumento máximo (+{$maxPct}%). Se omite.");
                continue;
            }

            $productAssignments[$product->id] = [
                'item'                 => $item,
                'product'              => $product,
                'quantity'             => $quantity,
                'selected_offer_index' => 0,
                'offers'               => $formattedOffers,
            ];
        }

        // 4. Algoritmo de Evaluación de Mínimos de Compra y Reasignación Inteligente
        $this->optimizarMinimosDeCompra($productAssignments);

        // 5. Persistir órdenes en base de datos
        $ordenesActualizadas = [];

        foreach ($productAssignments as $assignment) {
            $idx             = $assignment['selected_offer_index'];
            $chosenOffer     = $assignment['offers'][$idx] ?? null;

            if (!$chosenOffer || !$chosenOffer['supplier']) {
                continue;
            }

            $product         = $assignment['product'];
            $supplier        = $chosenOffer['supplier'];
            $productSupplier = $chosenOffer['productSupplier'];
            $unitCost        = (float) $chosenOffer['unit_cost'];
            $quantity        = (float) $assignment['quantity'];

            // Buscar o crear la AutoOrder para este proveedor (siempre PENDING)
            $autoOrder = AutoOrder::firstOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'status'      => AutoOrderStatus::PENDING,
                ],
                [
                    'order_date'     => now(),
                    'total_items'    => 0,
                    'total_quantity' => 0,
                    'total_amount'   => 0,
                ]
            );

            $ordenesActualizadas[$autoOrder->id] = $autoOrder;

            // Añadir o actualizar detalle
            $detail = AutoOrderDetail::where('order_id', $autoOrder->id)
                ->where('product_id', $product->id)
                ->first();

            $prodSuppId = $productSupplier ? $productSupplier->id : null;

            if ($detail) {
                $detail->quantity            += $quantity;
                $detail->unit_cost            = $unitCost;
                $detail->subtotal             = (float) $detail->quantity * $unitCost;
                if ($prodSuppId) {
                    $detail->product_suppliers_id = $prodSuppId;
                }
                $detail->save();
            } else {
                AutoOrderDetail::create([
                    'order_id'             => $autoOrder->id,
                    'product_id'           => $product->id,
                    'product_suppliers_id' => $prodSuppId,
                    'quantity'             => $quantity,
                    'unit_cost'            => $unitCost,
                    'subtotal'             => $quantity * $unitCost,
                ]);
            }

            // Limpiar manual_solicitar si estaba definido
            Product::where('id', $product->id)->update(['manual_solicitar' => null]);
        }

        // 6. Recalcular totales de todas las órdenes modificadas
        foreach ($ordenesActualizadas as $orden) {
            $this->recalcularTotales($orden);
        }

        return array_keys($ordenesActualizadas);
    }

    /**
     * Evalúa montos mínimos de compra por proveedor.
     * Si un proveedor no alcanza su mínimo de compra (y no es Drotaca):
     * - Si el producto tiene otro proveedor alternativo, se reasigna a ese proveedor.
     * - Si sólo ese proveedor tiene el producto, se mantiene con él para no dejar el producto sin pedir.
     */
    private function optimizarMinimosDeCompra(array &$productAssignments): void
    {
        $maxIterations = 10;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            $cambiosRealizados = false;

            // Calcular acumulados por proveedor
            $supplierTotals = [];
            $supplierObjects = [];
            $productsBySupplier = [];

            foreach ($productAssignments as $pId => $data) {
                $idx = $data['selected_offer_index'];
                $offer = $data['offers'][$idx] ?? null;
                if (!$offer || !$offer['supplier']) {
                    continue;
                }

                $sId = (int) $offer['supplier']->id;
                $supplierObjects[$sId] = $offer['supplier'];
                $subtotal = (float) ($offer['unit_cost'] * $data['quantity']);

                $supplierTotals[$sId] = ($supplierTotals[$sId] ?? 0.0) + $subtotal;
                $productsBySupplier[$sId][] = $pId;
            }

            // Evaluar cada proveedor que tenga productos asignados
            foreach ($supplierTotals as $sId => $totalMonto) {
                $supplier = $supplierObjects[$sId] ?? null;
                if (!$supplier) {
                    continue;
                }

                // Excepción para Drotaca: no se valida mínimo de compra
                if ($this->isDrotaca($supplier)) {
                    continue;
                }

                $minimoCompra = (float) ($supplier->min_order_amount ?? 0);
                if ($minimoCompra <= 0) {
                    continue;
                }

                // Si no alcanza el mínimo de compra requerido
                if ($totalMonto < $minimoCompra) {
                    $pIds = $productsBySupplier[$sId] ?? [];

                    foreach ($pIds as $pId) {
                        $offers = $productAssignments[$pId]['offers'] ?? [];
                        $totalOffers = count($offers);

                        // Si sólo este proveedor tiene el producto, queda con él
                        if ($totalOffers <= 1) {
                            continue;
                        }

                        // Buscar una alternativa disponible
                        $currentIdx = $productAssignments[$pId]['selected_offer_index'];
                        $bestAlternativeIdx = null;

                        for ($i = 0; $i < $totalOffers; $i++) {
                            if ($i === $currentIdx) {
                                continue;
                            }
                            $altSupplier = $offers[$i]['supplier'] ?? null;
                            if (!$altSupplier) {
                                continue;
                            }

                            // Prioridad 1: Proveedor alternativo que sea Drotaca o que ya cumpla su mínimo
                            $altSId = (int) $altSupplier->id;
                            $altTotal = $supplierTotals[$altSId] ?? 0.0;
                            $altMin = (float) ($altSupplier->min_order_amount ?? 0);

                            if ($this->isDrotaca($altSupplier) || $altMin <= 0 || $altTotal >= $altMin) {
                                $bestAlternativeIdx = $i;
                                break;
                            }

                            if ($bestAlternativeIdx === null) {
                                $bestAlternativeIdx = $i;
                            }
                        }

                        if ($bestAlternativeIdx !== null && $bestAlternativeIdx !== $currentIdx) {
                            $productAssignments[$pId]['selected_offer_index'] = $bestAlternativeIdx;
                            $cambiosRealizados = true;
                            $this->line("     🔄 Reasignando Producto [{$pId}] del proveedor '{$supplier->name}' (no alcanza mín. de \${$minimoCompra}) a '{$offers[$bestAlternativeIdx]['supplier']->name}'.");
                        }
                    }
                }
            }

            if (!$cambiosRealizados) {
                break;
            }
        }
    }

    /**
     * Determina si un proveedor corresponde a DROTACA.
     */
    private function isDrotaca(?\App\Models\Supplier $supplier): bool
    {
        if (!$supplier) {
            return false;
        }

        $name = mb_strtolower((string) $supplier->name, 'UTF-8');
        $social = mb_strtolower((string) ($supplier->social_reason ?? ''), 'UTF-8');

        return str_contains($name, 'drotaca') || str_contains($social, 'drotaca');
    }

    /**
     * Recalcula totales de la AutoOrder sumando sus detalles activos.
     */
    private function recalcularTotales(AutoOrder $order): void
    {
        $totals = AutoOrderDetail::where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total_items, SUM(quantity) as total_quantity, SUM(subtotal) as total_amount')
            ->first();

        $order->update([
            'total_items'    => $totals->total_items    ?? 0,
            'total_quantity' => $totals->total_quantity ?? 0,
            'total_amount'   => $totals->total_amount   ?? 0,
        ]);
    }
}
