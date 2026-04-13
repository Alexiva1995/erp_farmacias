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
    protected $signature = 'app:fix-bs-history';

    protected $description = 'Reconstruye el historial de montos USD para ventas en Bolivares (Bs) usando tasas mensuales historicas del BCV.';

    public function handle()
    {
        // Mapa de tasas medias mensuales BCV CORREGIDAS (Aproximadamente x10 segun realidad 2026)
        $rates = [
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

        $this->info("Iniciando CORRECCIÓN de reconstrucción histórica de Bs a USD...");
        
        $totalOrdersUpdated = 0;
        $totalDetailsUpdated = 0;

        foreach ($rates as $month => $rate) {
            $this->comment("Corrigiendo mes: {$month} (Tasa Real: {$rate} Bs/USD)");

            // Actualizar órdenes del mes (Forzando actualización)
            $affectedOrders = \DB::table('orders')
                ->where('currency', 'Bs')
                ->where('status', 'Completed')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$month])
                ->update([
                    'total_amount_usd' => \DB::raw("total_amount / {$rate}"),
                    'updated_at' => now()
                ]);

            // Actualizar detalles de órdenes del mes (Forzando actualización)
            $affectedDetails = \DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.currency', 'Bs')
                ->where('orders.status', 'Completed')
                ->whereRaw("DATE_FORMAT(orders.created_at, '%Y-%m') = ?", [$month])
                ->where('order_details.quantity', '>', 0)
                ->update([
                    'order_details.unit_price_usd' => \DB::raw("(order_details.price / order_details.quantity) / {$rate}"),
                    'order_details.updated_at' => now()
                ]);

            $totalOrdersUpdated += $affectedOrders;
            $totalDetailsUpdated += $affectedDetails;
        }

        $this->info("Finalizado con éxito:");
        $this->info("- Órdenes actualizadas: {$totalOrdersUpdated}");
        $this->info("- Detalles actualizados: {$totalDetailsUpdated}");
    }
}
