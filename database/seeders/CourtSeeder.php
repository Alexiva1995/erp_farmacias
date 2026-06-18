<?php

namespace Database\Seeders;

use App\Models\Court;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Court::firstOrCreate(
            ['name' => 'Cancha Grande (Fútbol 5)'],
            ['price' => 30.00]
        );

        Court::firstOrCreate(
            ['name' => 'Cancha Pequeña (Fútbol 5)'],
            ['price' => 20.00]
        );
    }
}
