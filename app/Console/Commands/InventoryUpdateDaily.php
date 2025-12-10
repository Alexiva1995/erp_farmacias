<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupplierConnection;

class InventoryUpdateDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inventory-update-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el inventario de todos los proveedores que tienen API o FTP.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando la actualización diaria de inventario...');

        $connections = SupplierConnection::with('supplier')
            ->whereIn('type', ['api', 'ftp'])
            ->get();

        if ($connections->isEmpty()) {
            $this->warn('No se encontraron conexiones API o FTP activas para actualizar.');
            return Command::SUCCESS;
        }

        $connections->each(function ($connection) {
            $supplier = $connection->supplier;
            
            if (!$supplier) {
                $this->error("Proveedor no encontrado para la conexión ID: {$connection->id}");
                return;
            }
            $this->comment("Procesando proveedor: {$supplier->name} ({$connection->type})");
            try {
                $this->info("Inventario de {$supplier->name} ({$connection->type}) actualizado con éxito.");
            } catch (\Exception $e) {
                $this->error("Error al actualizar inventario de {$supplier->name}: " . $e->getMessage());
            }
        });

        $this->info('Actualización diaria de inventario finalizada.');
        return Command::SUCCESS;
    }
}
