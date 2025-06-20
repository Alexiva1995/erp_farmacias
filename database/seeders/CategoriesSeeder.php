<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Category::truncate();

        $categories = [
            ['id' => 1, 'name' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ];

        Category::insert($categories);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
