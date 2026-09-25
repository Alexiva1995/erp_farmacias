<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeDisabledSupplierProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:purge-disabled-products {--force : Forzar ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina todas las ofertas y productos (product_suppliers) pertenecientes a proveedores inhabilitados (is_active = false)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Buscando proveedores inhabilitados...');

        $disabledSuppliers = Supplier::where('is_active', false)->get(['id', 'name', 'is_active']);

        if ($disabledSuppliers->isEmpty()) {
            $this->info('✅ No hay proveedores inhabilitados registrados en el sistema.');
            return self::SUCCESS;
        }

        $disabledIds = $disabledSuppliers->pluck('id')->toArray();
        $totalProductsCount = ProductSupplier::whereIn('supplier_id', $disabledIds)->count();

        $this->table(
            ['ID', 'Proveedor', 'Estado'],
            $disabledSuppliers->map(fn($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'is_active' => 'Inhabilitado (false)',
            ])->toArray()
        );

        $this->warn("⚠️  Se encontraron {$disabledSuppliers->count()} proveedores inhabilitados con un total de {$totalProductsCount} productos/ofertas en 'product_suppliers'.");

        if ($totalProductsCount === 0) {
            $this->info('✅ Los proveedores inhabilitados no tienen productos asociados en el catálogo.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('¿Desea proceder con la eliminación definitiva de estos productos de proveedores inhabilitados?', true)) {
            $this->warn('Operación cancelada por el usuario.');
            return self::SUCCESS;
        }

        $this->line('🗑️  Eliminando registros de product_suppliers...');

        $deletedCount = DB::transaction(function () use ($disabledIds) {
            return ProductSupplier::whereIn('supplier_id', $disabledIds)->delete();
        });

        $this->info("✅ Se han eliminado exitosamente {$deletedCount} productos/ofertas de proveedores inhabilitados.");

        Log::info("[PurgeDisabledSupplierProducts] Se eliminaron {$deletedCount} registros de product_suppliers de proveedores inactivos.", [
            'supplier_ids' => $disabledIds,
        ]);

        return self::SUCCESS;
    }
}
