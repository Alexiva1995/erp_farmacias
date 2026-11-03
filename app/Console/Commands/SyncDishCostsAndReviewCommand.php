<?php

namespace App\Console\Commands;

use App\Contracts\Dish as DishContract;
use Illuminate\Console\Command;

class SyncDishCostsAndReviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dishes:sync-costs-and-review';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula los costos de los platos con base en los costos actuales de los insumos y marca en revisión aquellos cuyo precio designado sea inferior al precio sugerido.';

    /**
     * Execute the console command.
     */
    public function handle(DishContract $dishService): int
    {
        $this->info('Iniciando sincronización de costos de platos y verificación de margen sugerido...');

        $result = $dishService->syncCostsAndFlagUnprofitableDishes();

        $this->info("Platos actualizados: {$result['updated_count']}.");
        $this->info("Platos marcados en revisión: {$result['flagged_under_review_count']}.");

        return Command::SUCCESS;
    }
}
