<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpensesCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Salarios', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Reposición', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Mantenimiento', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Impuestos', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Impuestos de oficina', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Limpieza', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Reacondicionamiento', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('expense_categories')->insert($data);

    }
}
