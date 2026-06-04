<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\Product;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Laboratory;
use Illuminate\Support\Str;

class ImportTofeData extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'import:tofe-data';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Importar categorías, productos y platos de la base de datos anterior (toffle)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        $this->info('Iniciando importación desde la base de datos Toffle...');

        // Configuración de la conexión temporal a MySQL para toffle_temp
        Config::set('database.connections.toffle_temp', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => 'toffle_temp',
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        $dbTemp = DB::connection('toffle_temp');

        // 1. Importación de Categorías
        $this->info('Importando categorías...');
        $oldCategories = $dbTemp->table('categories')->where('type', 1)->get();
        $categoryMapping = []; // old_id => new_id

        foreach ($oldCategories as $oldCat) {
            // Buscar o crear la categoría en la BD actual
            $newCat = Category::firstOrCreate([
                'name' => Str::upper(trim($oldCat->name)),
            ]);
            $categoryMapping[$oldCat->id] = $newCat->id;
        }
        $this->info('Categorías importadas correctamente.');

        // 2. Importación de Productos (inventarios + products)
        $this->info('Importando productos e ingredientes...');
        $oldInventories = $dbTemp->table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->select(
                'inventories.id as inventory_id',
                'inventories.flavor_name',
                'inventories.cost',
                'inventories.price',
                'inventories.unit_package',
                'products.name as product_name',
                'products.mark',
                'products.gr',
                'products.quantity'
            )
            ->get();

        $productMapping = []; // old_inventory_id => new_product_id

        foreach ($oldInventories as $oldInv) {
            $fullName = trim($oldInv->product_name);
            if ($oldInv->flavor_name) {
                $fullName .= ' - ' . trim($oldInv->flavor_name);
            }

            // Buscar o crear laboratorio (que actúa como marca)
            $laboratoryId = null;
            if ($oldInv->mark) {
                $labName = trim(Str::upper($oldInv->mark));
                if (!empty($labName)) {
                    $lab = Laboratory::firstOrCreate(['name' => $labName]);
                    $laboratoryId = $lab->id;
                }
            }

            // Determinar presentación y unidad de medida
            $presentation = 1.0;
            $unitOfMeasure = 'unid';

            if ($oldInv->gr !== null && is_numeric($oldInv->gr)) {
                $presentation = (float) $oldInv->gr;
                $unitOfMeasure = 'gr';
            } elseif ($oldInv->quantity !== null && is_numeric($oldInv->quantity)) {
                $presentation = (float) $oldInv->quantity;
                $unitOfMeasure = 'unid';
            }

            // El costo unitario proviene del costo de inventario antiguo
            $unitCost = $oldInv->cost > 0 ? (float) $oldInv->cost : (float) $oldInv->price;

            // Evitar duplicados revisando si ya existe un producto con el mismo nombre y presentación
            $newProduct = Product::where('name', Str::upper($fullName))
                ->where('presentation', $presentation)
                ->first();

            if (!$newProduct) {
                $newProduct = Product::create([
                    'name' => Str::upper($fullName),
                    'active_ingredient' => 'N/A',
                    'laboratory_id' => $laboratoryId,
                    'unit_cost' => $unitCost,
                    'sale_price' => (float) $oldInv->price,
                    'presentation' => $presentation,
                    'unit_of_measure' => $unitOfMeasure,
                    'is_active' => true,
                    'iva' => 0.0,
                ]);
            }

            $productMapping[$oldInv->inventory_id] = $newProduct->id;
        }
        $this->info('Productos e ingredientes importados correctamente.');

        // 3. Importación de Platos
        $this->info('Importando platos...');
        $oldDishes = $dbTemp->table('dishes')->get();

        foreach ($oldDishes as $oldDish) {
            $newCategoryId = $categoryMapping[$oldDish->category_id] ?? null;

            // Crear el plato en la base de datos actual
            $newDish = Dish::create([
                'name' => $oldDish->name,
                'cost_price' => (float) $oldDish->cost_price,
                'cpv' => $oldDish->cpv !== null ? (float) $oldDish->cpv : (float) $oldDish->cost_price,
                'suggested_price' => (float) $oldDish->suggested_price,
                'designated_price' => (float) $oldDish->designated_price,
                'percentage_profit' => $oldDish->percentage_profit,
                'category_id' => $newCategoryId,
                'status' => $oldDish->status,
            ]);

            // 4. Importación de los ingredientes del plato
            $oldIngredients = $dbTemp->table('dish_ingredient')
                ->where('dish_id', $oldDish->id)
                ->get();

            foreach ($oldIngredients as $oldIng) {
                $newProductId = $productMapping[$oldIng->inventory_id] ?? null;
                if ($newProductId) {
                    if (!$newDish->ingredients()->where('product_id', $newProductId)->exists()) {
                        $newDish->ingredients()->attach($newProductId, [
                            'portion' => (float) $oldIng->portion,
                            'designated_cost' => (float) $oldIng->designated_cost,
                        ]);
                    }
                }
            }
        }

        $this->info('¡Platos e ingredientes importados y adaptados con éxito!');
    }
}
