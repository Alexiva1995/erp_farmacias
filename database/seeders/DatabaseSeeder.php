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
            GroupsLaboratoriesSeeder::class,
            LaboratoriesSeeder::class,
            OriginsSeeder::class,
            CategoriesSeeder::class,
            ProductsSeeder::class,
            SuppliersSeeder::class,
            ProductLotsSeeder::class,
        ]);
    }
}