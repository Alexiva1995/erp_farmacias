<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialties = [
            'Medicina General',
            'Pediatría',
            'Cardiología',
            'Ginecología y Obstetricia',
            'Traumatología',
            'Dermatología',
            'Oftalmología',
            'Otorrinolaringología',
            'Psiquiatría',
            'Gastroenterología'
        ];

        foreach ($specialties as $name) {
            \App\Models\Specialty::firstOrCreate(['name' => $name]);
        }
    }
}
