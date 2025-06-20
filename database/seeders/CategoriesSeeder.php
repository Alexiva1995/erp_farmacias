<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Medicamentos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Cosméticos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Suplementos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Dispositivos Médicos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Farmacia Natural', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('categories')->insert($data);
    }
}
