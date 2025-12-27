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
            SuppliersSeeder::class,
            ProfitabilitySettingsSeeder::class,
            ExchangeRateSeeder::class,
            SupplierConnectionSeeder::class,
            RolesSeeder::class,
            CashClosingSeeder::class,
            ExpensesCategoriesSeeder::class,
            FiscalHistorySeeder::class,
            InvoiceSeeder::class,
            ProductsSeeder::class,
            ProductLotsSeeder::class,
            ClientsSeeder::class,
            GroupsProductsSeeder::class,
            MigrarDailyClosuresSeeder::class,
        ]);
    }
}
