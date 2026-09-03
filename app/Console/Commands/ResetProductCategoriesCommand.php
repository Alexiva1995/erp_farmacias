<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetProductCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:reset-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deja todos los productos sin categoría (category_id = NULL) y prepara las 17 categorías de e-commerce';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $count = \App\Models\Product::withoutGlobalScope('not_deleted')->whereNotNull('category_id')->count();
            \App\Models\Product::withoutGlobalScope('not_deleted')->update(['category_id' => null]);
            $this->info("¡Listo! Se dejaron todos los productos sin categoría (Total reseteados: {$count}).");

            // Asegurar que las 17 categorías existan
            $this->call('categories:renew-ecommerce');
        } catch (\Exception $e) {
            $this->error("Error al resetear categorías: " . $e->getMessage());
        }
    }
}
