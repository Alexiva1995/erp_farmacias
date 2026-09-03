<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncLotSuppliersCommand extends Command
{
    protected $signature = 'lots:sync-suppliers {--dry-run : Ejecutar simulación sin guardar cambios}';
    protected $description = 'Restaura y asocia los proveedores a los lotes de productos basándose en el historial de facturas de compra';

    public function handle(): int
    {
        $this->info('Iniciando sincronización de proveedores para lotes huérfanos o sin proveedor asignado...');
        $isDryRun = $this->option('dry-run');

        // 1. Fase 1: Coincidencia Exacta por Producto + Número de Lote
        $exactMatches = DB::select("
            SELECT pl.id as lot_id, inv.supplier_id, s.name as supplier_name
            FROM product_lots pl
            JOIN invoice_details id ON pl.product_id = id.product_id AND pl.lot_number = id.lot_number
            JOIN invoices inv ON id.invoice_id = inv.id
            JOIN suppliers s ON inv.supplier_id = s.id
            WHERE pl.supplier_id IS NULL
        ");

        $exactCount = 0;
        foreach ($exactMatches as $match) {
            if (!$isDryRun) {
                DB::table('product_lots')
                    ->where('id', $match->lot_id)
                    ->whereNull('supplier_id')
                    ->update(['supplier_id' => $match->supplier_id]);
            }
            $exactCount++;
        }

        $this->info("Fase 1 (Coincidencia exacta Producto + Lote): {$exactCount} lotes asignados.");

        // 2. Fase 2: Coincidencia por Última Factura de Compra del Producto
        $fallbackMatches = DB::select("
            SELECT pl.id as lot_id,
                   (
                       SELECT inv2.supplier_id
                       FROM invoice_details id2
                       JOIN invoices inv2 ON id2.invoice_id = inv2.id
                       WHERE id2.product_id = pl.product_id
                       ORDER BY inv2.created_at DESC
                       LIMIT 1
                   ) as supplier_id
            FROM product_lots pl
            WHERE pl.supplier_id IS NULL
        ");

        $fallbackCount = 0;
        foreach ($fallbackMatches as $fMatch) {
            if (!empty($fMatch->supplier_id)) {
                if (!$isDryRun) {
                    DB::table('product_lots')
                        ->where('id', $fMatch->lot_id)
                        ->whereNull('supplier_id')
                        ->update(['supplier_id' => $fMatch->supplier_id]);
                }
                $fallbackCount++;
            }
        }

        $this->info("Fase 2 (Coincidencia por Última Factura del Producto): {$fallbackCount} lotes asignados.");

        $totalRestored = $exactCount + $fallbackCount;
        $this->info("✅ Total de lotes actualizados con su proveedor real: {$totalRestored}");

        Log::info("[SyncLotSuppliers] Sincronización finalizada. {$totalRestored} lotes actualizados.");

        return 0;
    }
}
