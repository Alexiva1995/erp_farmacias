<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OriginsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cargar los datos desde el archivo JSON
        $json = File::get(database_path('data/provenance.json'));
        $data = json_decode($json, true);

        // Insertar en la base de datos
        DB::table('origins')->insert($data);
    }
}
