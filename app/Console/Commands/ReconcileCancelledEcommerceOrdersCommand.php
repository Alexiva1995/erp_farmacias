<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLot;
use App\Observers\ProductLotObserver;
use App\Services\Inventory\StockoutService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileCancelledEcommerceOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ecommerce:reconcile-cancelled 
                            {--days=1 : Cantidad de días hacia atrás a revisar (por defecto 1 = hoy)} 
                            {--order= : ID específico de orden e-commerce a conciliar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restaura inventario en lotes y genera movimientos de devolución en trazabilidad para pedidos e-commerce cancelados.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $specificOrderId = $this->option('order');

        $query = DB::table('ecommerce_orders')->where('status', 'Cancelled');

        if ($specificOrderId) {
            $query->where('id', (int) $specificOrderId);
        } else {
            $startDate = Carbon::now()->subDays(max(0, $days - 1))->startOfDay();
            $query->where('updated_at', '>=', $startDate);
        }

        $orders = $query->orderBy('id', 'desc')->get();

        if ($orders->isEmpty()) {
            $this->info('No se encontraron pedidos e-commerce cancelados para el rango seleccionado.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$orders->count()} pedido(s) e-commerce cancelado(s)...");

        $reconciledOrders = 0;
        $repairedItems = 0;

        foreach ($orders as $ecoOrder) {
            DB::beginTransaction();
            try {
                $tpvOrderId = data_get($ecoOrder, 'tpv_order_id');
                $tpvOrder = $tpvOrderId ? Order::find($tpvOrderId) : null;
                if (!$tpvOrder) {
                    $tpvOrder = Order::whereJsonContains('payment_methods', ['reference' => 'ECO-' . $ecoOrder->id])->first();
                }

                // Asegurar que la orden TPV esté marcada como cancelada
                if ($tpvOrder && strtolower($tpvOrder->status) !== 'cancelled') {
                    $tpvOrder->status = Order::CANCELLED;
                    $tpvOrder->save();
                }

                $items = DB::table('ecommerce_order_items')
                    ->where('ecommerce_order_id', $ecoOrder->id)
                    ->get();

                $orderHadRepairs = false;

                foreach ($items as $item) {
                    if (empty($item->product_id)) {
                        continue;
                    }

                    $product = Product::find($item->product_id);
                    if (!$product) {
                        continue;
                    }

                    // Verificar si ya existe movimiento de devolución registrado para este producto y orden
                    $existingMovement = InventoryMovement::where('product_id', $product->id)
                        ->where('movement_type', 'return')
                        ->where(function ($q) use ($tpvOrder, $ecoOrder) {
                            if ($tpvOrder) {
                                $q->where('order_id', $tpvOrder->id);
                            }
                            $q->orWhereDate('movement_date', '>=', Carbon::parse($ecoOrder->updated_at)->toDateString());
                        })
                        ->where('quantity', $item->quantity)
                        ->first();

                    if ($existingMovement) {
                        continue;
                    }

                    // Buscar o crear lote para la devolución
                    $productLot = ProductLot::where('product_id', $product->id)
                        ->where(function ($q) {
                            $q->whereNull('expiration_date')
                                ->orWhere('expiration_date', '>', Carbon::now());
                        })
                        ->orderBy('expiration_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->first()
                        ?? ProductLot::where('product_id', $product->id)->orderBy('id', 'desc')->first();

                    if (!$productLot) {
                        $productLot = ProductLot::create([
                            'product_id'      => $product->id,
                            'lot_number'      => 'LOT-RETURN-' . $product->id,
                            'quantity'        => 0,
                            'expiration_date' => Carbon::now()->addYears(2),
                            'cost_price'      => $product->cost_price ?? 0,
                        ]);
                    }

                    $stockBefore = (float) ($product->lots()->sum('quantity') ?? 0);

                    // Devolver cantidad al lote de forma segura
                    ProductLotObserver::$isReturningLot = true;
                    try {
                        $productLot->increment('quantity', $item->quantity);
                    } finally {
                        ProductLotObserver::$isReturningLot = false;
                    }

                    $totalStock = (float) ($product->lots()->sum('quantity') ?? 0);
                    $product->updateQuietly(['stock' => $totalStock]);
                    StockoutService::syncStockout($product, $totalStock);

                    // Registrar movimiento de devolución en Kardex / trazabilidad
                    InventoryMovement::create([
                        'product_id'     => $product->id,
                        'product_lot_id' => $productLot->id,
                        'movement_type'  => 'return',
                        'quantity'       => $item->quantity,
                        'invoice_id'     => null,
                        'supplier_id'    => null,
                        'order_id'       => $tpvOrder?->id,
                        'user_id'        => 1,
                        'stock_before'   => $stockBefore,
                        'stock_after'    => $totalStock,
                        'movement_date'  => Carbon::parse($ecoOrder->updated_at ?? now()),
                    ]);

                    $repairedItems++;
                    $orderHadRepairs = true;
                }

                DB::commit();

                if ($orderHadRepairs) {
                    $reconciledOrders++;
                    $this->line(" ✔ Pedido e-commerce #{$ecoOrder->id} conciliado: inventario y trazabilidad restaurados.");
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error(" ✖ Error en pedido e-commerce #{$ecoOrder->id}: " . $e->getMessage());
                Log::error("[ReconcileCancelledEcommerce] Error en orden {$ecoOrder->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Conciliación finalizada con éxito.");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Pedidos Conciliados', $reconciledOrders],
                ['Productos Restaurados a Lotes/Kardex', $repairedItems],
            ]
        );

        return self::SUCCESS;
    }
}
