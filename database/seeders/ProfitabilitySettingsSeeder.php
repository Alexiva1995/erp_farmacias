<?php

namespace Database\Seeders;

use App\Models\ProfitabilitySetting;
use Illuminate\Database\Seeder;

class ProfitabilitySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProfitabilitySetting::create([
            'default_profitability_percentage' => '25',
        ]);
    }
}
