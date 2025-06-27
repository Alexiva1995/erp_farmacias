<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LaboratoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener los IDs de los grupos de la tabla groups_laboratories
        $groupIds = DB::table('groups_laboratories')->pluck('id')->toArray();

        // Cargar los datos desde el archivo JSON
        $json = File::get(database_path('data/laboratory.json'));
        $data = json_decode($json, true);

        // Asignar group_id aleatorio y timestamps
        foreach ($data as &$lab) {
            $lab['group_id'] = $groupIds[array_rand($groupIds)];
        }

        // Insertar en la base de datos
        DB::table('laboratories')->insert($data);
    }
}
