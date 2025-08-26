<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfitabilitySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('profitability_settings')->insert([
            'default_profitability_percentage' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
