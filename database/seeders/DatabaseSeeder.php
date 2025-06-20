<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategoriesSeeder::class,
            LaboratoriesSeeder::class,
            OriginsSeeder::class,
            ProductsSeeder::class,
            ProductLotsSeeder::class,
        ]);

        $this->call(GroupsLaboratoriesSeeder::class);
        $this->call(LaboratoriesSeeder::class);
        $this->call(OriginsSeeder::class);
        $this->call(CategoriesSeeder::class);
        $this->call(ProductsSeeder::class);
        $this->call(SuppliersSeeder::class);
        $this->call(ProductLotsSeeder::class);
    }
}
