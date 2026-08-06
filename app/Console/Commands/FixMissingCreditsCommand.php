<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Credit;
use Carbon\Carbon;

class FixMissingCreditsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:fix-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta órdenes pagadas con método de crédito que no poseen su registro correspondiente en la tabla credits y los crea.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Buscando órdenes a crédito sin registro en la tabla de créditos...");

        $orders = Order::whereNotNull('payment_methods')->get();
        $createdCount = 0;

        foreach ($orders as $order) {
            $paymentMethods = is_array($order->payment_methods) 
                ? $order->payment_methods 
                : json_decode((string) $order->payment_methods, true);

            if (!is_array($paymentMethods)) {
                continue;
            }

            $hasCredit = collect($paymentMethods)->contains(function ($p) {
                $method = is_array($p) ? ($p['method'] ?? '') : ($p->method ?? '');
                return strtolower((string) $method) === 'credit';
            });

            if ($hasCredit) {
                $existingCredit = Credit::where('order_id', $order->id)->first();

                if (!$existingCredit) {
                    Credit::create([
                        'client_id' => $order->client_id,
                        'order_id' => $order->id,
                        'credit_amount' => (float) $order->total_amount,
                        'pending_amount' => (float) $order->total_amount,
                        'credit_date' => $order->created_at ?? Carbon::now(),
                        'status' => 'Active',
                    ]);

                    $this->info("Crédito creado para la Orden #{$order->id} (Cliente ID: {$order->client_id}, Monto: {$order->total_amount})");
                    $createdCount++;
                }
            }
        }

        $this->info("Proceso finalizado. Total de créditos recuperados y creados: {$createdCount}");
        return 0;
    }
}
