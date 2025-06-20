<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GroupsLaboratoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Grupo Farmacéutico Global', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Laboratorios Innovadores S.A.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Grupo BioMedic', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Farmacorp Internacional', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Laboratorios Genéricos Plus', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('groups_laboratories')->insert($data);
    }
}
