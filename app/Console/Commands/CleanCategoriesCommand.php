<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanCategoriesCommand extends Command
{
    /**
     * El nombre y la firma del comando en la consola.
     *
     * @var string
     */
    protected $signature = 'inventory:clean-categories';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Traslada las categorías operacionales/gastos a expense_categories y las limpia de la tabla categories (para productos).';

    /**
     * Categorías que pertenecen a Gastos y Operaciones, no a Productos/Platos.
     *
     * @var array
     */
    protected array $expenseNames = [
        'abono deuda',
        'Alquiler',
        'Automovil',
        'Salarios',
        'Servicios',
        'Gastos Fijos',
        'Gastos Reposición',
        'Limpieza',
        'Envases',
        'Domicilios',
        'Adicionales',
        'Artefactos'
    ];

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $this->info('--- LIMPIANDO Y TRASLADANDO CATEGORÍAS ---');

        try {
            DB::transaction(function () {
                // 1. Mover a la tabla expense_categories si no existen
                foreach ($this->expenseNames as $name) {
                    $exists = DB::table('expense_categories')
                        ->where('name', $name)
                        ->exists();

                    if (!$exists) {
                        DB::table('expense_categories')->insert([
                            'name' => $name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->line("Insertada en expense_categories: {$name}");
                    } else {
                        $this->line("Ya existe en expense_categories: {$name}");
                    }
                }

                // 2. Eliminar de la tabla categories
                $deletedCount = DB::table('categories')
                    ->whereIn('name', $this->expenseNames)
                    ->delete();

                $this->info("Eliminadas de la tabla de productos (categories): {$deletedCount} categorías.");
            });

            $this->info('¡Operación completada con éxito!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Error al limpiar categorías: ' . $e->getMessage());
            return 1;
        }
    }
}
