<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use App\Models\DailyCashClosure;
use App\Models\CashClosing;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsolidateAugust2026Data extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmacia:consolidate-august-2026';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolida la factura de Dronena, el stock de Aflamax 20 y los 31 días de cierres de caja de Agosto 2026';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando consolidación de datos de Agosto 2026...');

        DB::beginTransaction();
        try {
            // 1. Corrección de Factura Dronena A43141525 (ID 6395)
            $this->info('1. Corrigiendo factura de Dronena A43141525...');
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
                $this->line("   -> Factura Dronena corregida a Bs. {$montoBs} (\${$montoUsdReal} USD).");
            } else {
                $this->warn('   -> Factura Dronena A43141525 no encontrada (omitido).');
            }

            // 2. Corrección de Aflamax 50mg x 20 (ID 10802) a 73 unidades
            $this->info('2. Corrigiendo stock y lotes de Aflamax 50mg x 20...');
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
                    $this->line("   -> Stock y lote #{$activeLot->lot_number} ajustados a 73 unidades.");
                }
            }

            // 3. Consolidación de Cierres de Caja de Agosto (31 Días)
            $this->info('3. Consolidando los 31 días de cierres diarios de Agosto...');
            
            // Reasignar cierre 1189 al 31 de agosto
            $c1189 = DailyCashClosure::find(1189);
            if ($c1189) {
                $c1189->update([
                    'created_at' => '2026-08-31 23:59:59',
                    'updated_at' => '2026-08-31 23:59:59',
                ]);
            }

            // Reasignar cierre 1159 al 31 de julio
            $c1159 = DailyCashClosure::find(1159);
            if ($c1159) {
                $c1159->update([
                    'created_at' => '2026-07-31 23:59:59',
                    'updated_at' => '2026-07-31 23:59:59',
                ]);
            }

            // Generar o ajustar el cierre del 03 de Agosto
            $day3Closure = DailyCashClosure::whereDate('created_at', '2026-08-03')->first();
            if (!$day3Closure) {
                $day3Closings = CashClosing::whereIn('id', [7856, 8123, 8241, 8296, 8308])->get();

                $dailyCashClosureInstance = new DailyCashClosure();
                $TotalCopPaymentInUsd = $dailyCashClosureInstance->getTotalCopPaymentInUsd($day3Closings);
                $TotalBsPaymentInUsd = $dailyCashClosureInstance->getTotalBsPaymentInUsd($day3Closings);
                $TotalCopDeliveryInUsd = $dailyCashClosureInstance->getTotalCopDeliveryInUsd($day3Closings);
                $TotalBsDeliveryInUsd = $dailyCashClosureInstance->getTotalBsDeliveryInUsd($day3Closings);

                $day3Closure = DailyCashClosure::create([
                    'total_sales'          => $day3Closings->sum('total_sales') > 0 ? $day3Closings->sum('total_sales') : 284.00,
                    'total_usd'            => $day3Closings->sum('total_usd') > 0 ? $day3Closings->sum('total_usd') : 76.10,
                    'total_cop'            => $day3Closings->sum('total_cop') > 0 ? $day3Closings->sum('total_cop') : 655988.00,
                    'total_bs'             => $day3Closings->sum('total_bs') > 0 ? $day3Closings->sum('total_bs') : 10634.50,
                    'bs_card'              => $day3Closings->sum('bs_card_debito') + $day3Closings->sum('bs_card_credit') ?: 6050.20,
                    'bs_cash'              => $day3Closings->sum('bs_cash'),
                    'bs_card_debito'       => $day3Closings->sum('bs_card_debito') ?: 6050.20,
                    'bs_card_credit'       => $day3Closings->sum('bs_card_credit'),
                    'bs_transfer'          => $day3Closings->sum('bs_transfer'),
                    'bs_mobile'            => $day3Closings->sum('bs_mobile') ?: 4584.30,
                    'usd_cash'             => $day3Closings->sum('usd_cash') ?: 10.00,
                    'usd_transfer'         => $day3Closings->sum('usd_transfer'),
                    'usd_paypal'           => $day3Closings->sum('usd_paypal'),
                    'usd_binance'          => $day3Closings->sum('usd_binance'),
                    'cop_cash'             => $day3Closings->sum('cop_cash') ?: 597744.00,
                    'cop_transfer'         => $day3Closings->sum('cop_transfer'),
                    'cop_spare'            => $day3Closings->sum('cop_spare') ?: 36188.00,
                    'usd_delivered'        => $day3Closings->sum('usd_delivered') ?: 62.00,
                    'cop_delivered'        => $day3Closings->sum('cop_delivered') ?: 632600.00,
                    'bs_delivered'         => $day3Closings->sum('bs_delivered'),
                    'total_credits'        => $day3Closings->sum('usd_credit') ?: 72.81,
                    'total_payment_credit' => $day3Closings->sum('usd_transfer_payment_credit') + $day3Closings->sum('usd_cash_payment_credit') + $TotalCopPaymentInUsd + $TotalBsPaymentInUsd ?: 52.42,
                    'total_delivery'       => $TotalCopDeliveryInUsd + $day3Closings->sum('usd_delivered') + $TotalBsDeliveryInUsd ?: 265.81,
                    'created_at'           => '2026-08-03 23:59:59',
                    'updated_at'           => '2026-08-03 23:59:59',
                ]);

                foreach ($day3Closings as $dc) {
                    $dc->update(['daily_closure_id' => $day3Closure->id]);
                }
            }
            $this->line("   -> Cierres de caja alineados a 31 días completos.");

            DB::commit();
            $this->info('Consolidación finalizada con éxito.');
            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error durante la consolidación: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
