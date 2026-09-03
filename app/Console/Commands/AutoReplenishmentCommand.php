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

        $this->info("Iniciando reposición automática — {$configs->count()} configuración(es).");

        foreach ($configs as $config) {
            $this->procesarConfig($config);
        }

        $this->info('✅ Reposición automática completada.');
        return self::SUCCESS;
    }

    private function procesarConfig(AutoReplenishmentConfig $config): void
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
                return;
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
                return;
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

        } catch (\Exception $e) {
            $this->error("     ❌ Error en '{$config->name}': " . $e->getMessage());
            Log::error("[AutoReplenishment] Error en config {$config->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
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
     * Replica exactamente la lógica de IaAssistantActionController::addMultipleToOrder().
     */
    private function crearOrdenes($aReponer, AutoReplenishmentConfig $config): array
    {
        $ordenesActualizadas = [];
        $conDescuento        = $config->con_descuento;

        foreach ($aReponer as $item) {
            $product         = $item->product ?? $item;
            $bestSupplier    = $item->best_supplier;
            $productSupplier = $item->product_supplier ?? null;

            // Validación de seguridad: omitir productos bloqueados por vencimiento < 120 días
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

            // Obtener el ProductSupplier completo si no vino hidratado
            if (!$productSupplier && $bestSupplier) {
                $productSupplier = ProductSupplier::where('product_id', $product->id)
                    ->where('supplier_id', $bestSupplier->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->where(function ($q) {
                        $q->where('unit_cost_usd', '>', 0)
                          ->orWhere('unit_cost_usd_with_discount', '>', 0);
                    })
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$productSupplier || !$bestSupplier) {
                continue;
            }

            // Calcular costo unitario (misma prioridad que el controller)
            $unitCost = $conDescuento && $productSupplier->unit_cost_usd_with_discount > 0
                ? (float) $productSupplier->unit_cost_usd_with_discount
                : ($productSupplier->unit_cost_usd > 0
                    ? (float) $productSupplier->unit_cost_usd
                    : (float) ($product->unit_cost ?? 0));

            $quantity = (float) ($item->solicitar ?? $product->solicitar ?? 1);
            $quantity = max(1, ceil($quantity));

            // Buscar o crear la AutoOrder para este proveedor (siempre PENDING)
            $autoOrder = AutoOrder::firstOrCreate(
                [
                    'supplier_id' => $bestSupplier->id,
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

            // Añadir o actualizar el detalle del producto en la orden
            $detail = AutoOrderDetail::where('order_id', $autoOrder->id)
                ->where('product_id', $product->id)
                ->first();

            if ($detail) {
                // Si ya estaba en la orden, acumular cantidad
                $detail->quantity          += $quantity;
                $detail->unit_cost          = $unitCost;
                $detail->subtotal           = (float) $detail->quantity * $unitCost;
                $detail->product_suppliers_id = $productSupplier->id;
                $detail->save();
            } else {
                AutoOrderDetail::create([
                    'order_id'             => $autoOrder->id,
                    'product_id'           => $product->id,
                    'product_suppliers_id' => $productSupplier->id,
                    'quantity'             => $quantity,
                    'unit_cost'            => $unitCost,
                    'subtotal'             => $quantity * $unitCost,
                ]);
            }

            // Limpiar manual_solicitar si estaba definido
            Product::where('id', $product->id)->update(['manual_solicitar' => null]);
        }

        // Recalcular totales de todas las órdenes modificadas
        foreach ($ordenesActualizadas as $orden) {
            $this->recalcularTotales($orden);
        }

        return array_keys($ordenesActualizadas);
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
