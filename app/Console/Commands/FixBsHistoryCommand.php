<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixBsHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recalculate-historical-usd {--date=2026-02-01 : Fecha límite hacia atrás para procesar}';

    protected $description = 'Reconstruye el historial de montos USD para ventas antiguas en Bs y COP que no tienen valor en dólares.';

    public function handle()
    {
        $limitDate = $this->option('date');
        
        // Mapa de tasas medias mensuales BCV CORREGIDAS
        $bsRates = [
            '2026-04' => 476.43,
            '2026-03' => 470.00,
            '2026-02' => 460.00,
            '2026-01' => 450.00,
            '2025-12' => 430.00,
            '2025-11' => 385.00,
            '2025-10' => 382.00,
            '2025-09' => 368.00,
            '2025-08' => 366.00,
            '2025-07' => 365.00,
            '2025-06' => 364.00,
            '2025-05' => 362.00,
            '2025-04' => 361.00,
            '2025-03' => 361.00,
            '2025-02' => 360.00,
            '2025-01' => 360.00,
            '2024-12' => 359.00,
            '2024-06' => 360.00,
            '2024-01' => 355.00,
            '2023-12' => 350.00,
            '2023-01' => 195.00,
            '2022-12' => 150.00,
        ];

        $copRate = 4000;

        $this->info("Iniciando reconstrucción segura de historial USD...");
        $this->comment("Fecha límite: {$limitDate} (Procesando solo registros anteriores)");

        $totalOrdersUpdated = 0;
        $totalDetailsUpdated = 0;

        // 1. PROCESAR BOLIVARES (BS) DINÁMICAMENTE
        foreach ($bsRates as $month => $rate) {
            $this->line("Procesando Bs para el mes: {$month} (Tasa: {$rate})");

            $affectedOrders = \DB::table('orders')
                ->where('currency', 'Bs')
                ->where('status', 'Completed')
                ->where('order_date', '<', $limitDate)
                ->whereRaw("DATE_FORMAT(order_date, '%Y-%m') = ?", [$month])
                ->where(function($q) {
                    $q->where('total_amount_usd', 0)
                      ->orWhereNull('total_amount_usd');
                })
                ->update([
                    'total_amount_usd' => \DB::raw("total_amount / {$rate}"),
                    'updated_at' => now()
                ]);

            $affectedDetails = \DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.currency', 'Bs')
                ->where('orders.status', 'Completed')
                ->where('orders.order_date', '<', $limitDate)
                ->whereRaw("DATE_FORMAT(orders.order_date, '%Y-%m') = ?", [$month])
                ->where(function($q) {
                    $q->where('order_details.unit_price_usd', 0)
                      ->orWhereNull('order_details.unit_price_usd');
                })
                ->where('order_details.quantity', '>', 0)
                ->update([
                    'order_details.unit_price_usd' => \DB::raw("(order_details.price / order_details.quantity) / {$rate}"),
                    'order_details.updated_at' => now()
                ]);

            $totalOrdersUpdated += $affectedOrders;
            $totalDetailsUpdated += $affectedDetails;
        }

        // 2. PROCESAR PESOS (COP) TASA FIJA
        $this->info("Procesando Pesos (COP) con tasa fija 4000...");
        
        $affectedOrdersCop = \DB::table('orders')
            ->where('currency', 'COP')
            ->where('status', 'Completed')
            ->where('order_date', '<', $limitDate)
            ->where(function($q) {
                $q->where('total_amount_usd', 0)
                  ->orWhereNull('total_amount_usd');
            })
            ->update([
                'total_amount_usd' => \DB::raw("total_amount / {$copRate}"),
                'updated_at' => now()
            ]);

        $affectedDetailsCop = \DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.currency', 'COP')
            ->where('orders.status', 'Completed')
            ->where('orders.order_date', '<', $limitDate)
            ->where(function($q) {
                $q->where('order_details.unit_price_usd', 0)
                  ->orWhereNull('order_details.unit_price_usd');
            })
            ->where('order_details.quantity', '>', 0)
            ->update([
                'order_details.unit_price_usd' => \DB::raw("(order_details.price / order_details.quantity) / {$copRate}"),
                'order_details.updated_at' => now()
            ]);

        $totalOrdersUpdated += $affectedOrdersCop;
        $totalDetailsUpdated += $affectedDetailsCop;

        $this->info("Finalizado con éxito:");
        $this->info("- Órdenes actualizadas: {$totalOrdersUpdated}");
        $this->info("- Detalles actualizados: {$totalDetailsUpdated}");
    }
}

        $this->info("Finalizado con éxito:");
        $this->info("- Órdenes actualizadas: {$totalOrdersUpdated}");
        $this->info("- Detalles actualizados: {$totalDetailsUpdated}");
    }
}
