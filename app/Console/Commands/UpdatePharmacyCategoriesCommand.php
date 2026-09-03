<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdatePharmacyCategoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:renew-ecommerce';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renueva las categorías con las 17 opciones detalladas de e-commerce';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            \App\Models\Product::whereNotNull('category_id')->update(['category_id' => null]);
            \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            \App\Models\Category::query()->delete();
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE categories AUTO_INCREMENT = 1;');
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

            $categories = [
                'Cardiovascular e Hipertensión',
                'Gastrointestinal y Digestivo',
                'Dolor, Inflamación y Fiebre',
                'Respiratorio y Gripe',
                'Antibióticos y Antivirales',
                'Diabetes y Endocrinología',
                'Dermatología y Cuidado de la Piel',
                'Salud Femenina y Masculina',
                'Oftalmología y Salud Ocular',
                'Salud Mental y Sistema Nervioso',
                'Multivitamínicos y Minerales',
                'Salud Inmune y Defensas',
                'Nutrición Deportiva y Energía',
                'Botiquín y Primeros Auxilios',
                'Higiene y Cuidado Diario',
                'Mamá y Bebé',
                'Salud Sexual',
            ];

            foreach ($categories as $cat) {
                \App\Models\Category::create(['name' => $cat]);
            }

            $this->info("Categorías creadas con éxito: " . \App\Models\Category::count());
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
}
