<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearSupplierDiscountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:clear-discounts {--supplier= : ID específico de proveedor a limpiar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia todas las reglas de pago, descuentos de proveedores y restablece los precios con descuento al precio full';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $supplierId = $this->option('supplier');

        $this->info('Iniciando limpieza de reglas y descuentos...');

        try {
            DB::transaction(function () use ($supplierId) {
                // 1. Limpiar supplier_discounts
                $discountsQuery = DB::table('supplier_discounts');
                if ($supplierId) {
                    $discountsQuery->where('supplier_id', $supplierId);
                }
                $deletedDiscounts = $discountsQuery->delete();
                $this->line(" - {$deletedDiscounts} registros eliminados de supplier_discounts.");

                // 2. Limpiar payment_rules
                $rulesQuery = DB::table('payment_rules');
                if ($supplierId) {
                    $rulesQuery->where('supplier_id', $supplierId);
                }
                $deletedRules = $rulesQuery->delete();
                $this->line(" - {$deletedRules} registros eliminados de payment_rules.");

                // 3. Restablecer product_suppliers a precio full
                $productsQuery = DB::table('product_suppliers');
                if ($supplierId) {
                    $productsQuery->where('supplier_id', $supplierId);
                }
                $affectedProducts = $productsQuery->update([
                    'unit_cost_with_discount' => DB::raw('unit_cost'),
                    'unit_cost_usd_with_discount' => DB::raw('unit_cost_usd'),
                    'updated_at' => now(),
                ]);
                $this->line(" - {$affectedProducts} ofertas de proveedores restablecidas a precio full.");
            });

            $this->info('✓ Limpieza de descuentos completada exitosamente.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante la limpieza: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
