<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GroupsProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Analgésicos y Antiinflamatorios', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Antibióticos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Antihistamínicos y Antialérgicos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Suplementos y Vitaminas', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Productos Dermatológicos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Equipo Médico y Material Quirúrgico', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Productos de Cuidado Personal', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Antisépticos y Desinfectantes', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Productos Naturales y Fitoterapéuticos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Medicamentos de Uso Veterinario', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('groups_products')->insert($data);
    }
}
