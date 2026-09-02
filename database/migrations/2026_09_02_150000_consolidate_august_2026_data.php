<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use App\Models\DailyCashClosure;
use App\Models\CashClosing;
use App\Models\Order;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Corrección de Factura Dronena A43141525 (ID 6395)
        $invoice = Invoice::where('invoice_number', 'A43141525')
            ->orWhere('id', 6395)
            ->first();

        if ($invoice) {
            $rate = ExchangeRate::where('currency_code', 'BS')
                ->whereDate('created_at', '<=', '2026-08-14')
                ->orderBy('created_at', 'desc')
                ->first();

            $tasaBcv = $rate ? (float) $rate->rate : 771.0714;
            $montoBs = 2587.48;
            $montoUsdReal = round($montoBs / $tasaBcv, 2);

            $invoice->update([
                'currency' => 'Bs',
                'total_amount' => $montoBs,
                'total_usd' => $montoUsdReal,
                'outstanding_debt' => $montoBs,
                'exchange_rate' => $tasaBcv,
            ]);
        }

        // 2. Corrección de Aflamax 50mg x 20 (ID 10802) a 73 unidades
        $product = Product::find(10802);
        if ($product) {
            $activeLot = ProductLot::where('product_id', 10802)->where('id', 16823)->first();
            if (!$activeLot) {
                $activeLot = ProductLot::where('product_id', 10802)->orderBy('id', 'desc')->first();
            }

            if ($activeLot) {
                $oldStock = (float) $product->stock;
                $newStock = 73.0;
                $diffQty = $newStock - $oldStock;

                $activeLot->update(['quantity' => $newStock]);
                ProductLot::where('product_id', 10802)->where('id', '!=', $activeLot->id)->where('quantity', '>', 0)->update(['quantity' => 0]);
                $product->update(['stock' => $newStock]);

                if ($diffQty != 0) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'product_lot_id' => $activeLot->id,
                        'movement_type' => 'adjustment',
                        'quantity' => $diffQty,
                        'stock_before' => $oldStock,
                        'stock_after' => $newStock,
                        'movement_date' => Carbon::now(),
                        'user_id' => 1,
                    ]);
                }
            }
        }

        // 3. Consolidación de Cierres de Caja de Agosto (31 Días)
        $cSept1Early = DailyCashClosure::where(function($q) {
            $q->whereDate('created_at', '2026-09-01')
              ->whereTime('created_at', '<', '12:00:00');
        })->orWhere('id', 1189)->get();

        foreach ($cSept1Early as $c) {
            $c->update([
                'created_at' => '2026-08-31 23:59:59',
                'updated_at' => '2026-08-31 23:59:59',
            ]);
        }

        $cAug1Early = DailyCashClosure::where(function($q) {
            $q->whereDate('created_at', '2026-08-01')
              ->whereTime('created_at', '<', '12:00:00');
        })->orWhere('id', 1159)->get();

        foreach ($cAug1Early as $c) {
            $c->update([
                'created_at' => '2026-07-31 23:59:59',
                'updated_at' => '2026-07-31 23:59:59',
            ]);
        }

        // Generar o asegurar el cierre del 03 de Agosto
        $day3Closure = DailyCashClosure::whereDate('created_at', '2026-08-03')->first();
        if (!$day3Closure) {
            DailyCashClosure::create([
                'total_sales'          => 284.00,
                'total_usd'            => 76.10,
                'total_cop'            => 655988.00,
                'total_bs'             => 10634.50,
                'bs_card'              => 6050.20,
                'bs_cash'              => 0.00,
                'bs_card_debito'       => 6050.20,
                'bs_card_credit'       => 0.00,
                'bs_transfer'          => 0.00,
                'bs_mobile'            => 4584.30,
                'usd_cash'             => 10.00,
                'usd_transfer'         => 0.00,
                'usd_paypal'           => 0.00,
                'usd_binance'          => 0.00,
                'cop_cash'             => 597744.00,
                'cop_transfer'         => 0.00,
                'cop_spare'            => 36188.00,
                'usd_delivered'        => 62.00,
                'cop_delivered'        => 632600.00,
                'bs_delivered'         => 0.00,
                'total_credits'        => 72.81,
                'total_payment_credit' => 52.42,
                'total_delivery'       => 265.81,
                'created_at'           => '2026-08-03 23:59:59',
                'updated_at'           => '2026-08-03 23:59:59',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed on down
    }
};
