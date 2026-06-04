<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Dish;

class CleanSingleIngredientDishes extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'dishes:clean-single-ingredients';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Eliminar recetas de platos que contienen un solo ingrediente/producto';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza de platos con un único ingrediente...');

        // Obtener IDs de platos que tienen exactamente 1 ingrediente
        $singleIngredientDishIds = DB::table('dish_ingredients')
            ->select('dish_id')
            ->groupBy('dish_id')
            ->havingRaw('COUNT(*) = 1')
            ->pluck('dish_id')
            ->toArray();

        $count = count($singleIngredientDishIds);

        if ($count === 0) {
            $this->info('No se encontraron platos con un único ingrediente.');
            return;
        }

        $this->warn("Se encontraron {$count} platos para eliminar.");

        DB::transaction(function () use ($singleIngredientDishIds) {
            // Eliminar registros de la tabla pivote
            DB::table('dish_ingredients')
                ->whereIn('dish_id', $singleIngredientDishIds)
                ->delete();

            // Eliminar los platos
            DB::table('dishes')
                ->whereIn('id', $singleIngredientDishIds)
                ->delete();
        });

        $this->info("¡Se han eliminado correctamente {$count} platos y sus respectivas relaciones de ingredientes!");
    }
}
