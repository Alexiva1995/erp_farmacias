<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Origin;

class OriginsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Origin::truncate();

        $origins = [
            ['id' => 1, 'name' => 'ECUADOR', 'created_at' => '2024-12-21 00:00:02', 'updated_at' => '2024-12-21 00:00:02'],
            ['id' => 2, 'name' => 'VENEZUELA', 'created_at' => '2024-12-21 16:23:44', 'updated_at' => '2024-12-21 16:23:44'],
            ['id' => 3, 'name' => 'COLOMBIA', 'created_at' => '2024-12-21 16:37:55', 'updated_at' => '2024-12-21 16:37:55'],
            ['id' => 4, 'name' => 'CHINA', 'created_at' => '2024-12-21 17:10:09', 'updated_at' => '2024-12-21 17:10:09'],
            ['id' => 5, 'name' => 'INDIA', 'created_at' => '2024-12-21 17:10:34', 'updated_at' => '2024-12-21 17:10:34'],
            ['id' => 6, 'name' => 'FRANCIA', 'created_at' => '2024-12-21 17:25:10', 'updated_at' => '2024-12-21 17:25:10'],
            ['id' => 7, 'name' => 'BRASIL', 'created_at' => '2024-12-21 18:11:58', 'updated_at' => '2024-12-21 18:11:58'],
            ['id' => 8, 'name' => 'MEXICO', 'created_at' => '2024-12-21 19:31:45', 'updated_at' => '2024-12-21 19:31:45'],
            ['id' => 9, 'name' => 'CHILE', 'created_at' => '2024-12-21 20:05:10', 'updated_at' => '2024-12-21 20:05:10'],
            ['id' => 12, 'name' => 'ESTADOS UNIDOS', 'created_at' => '2024-12-23 18:24:39', 'updated_at' => '2024-12-23 18:24:39'],
            ['id' => 13, 'name' => 'CHILE', 'created_at' => '2024-12-23 18:34:05', 'updated_at' => '2024-12-23 18:34:05'],
            ['id' => 14, 'name' => 'VENEZUELA', 'created_at' => '2024-12-23 19:50:54', 'updated_at' => '2024-12-23 19:50:54'],
            ['id' => 15, 'name' => 'INDIA', 'created_at' => '2024-12-23 21:29:16', 'updated_at' => '2024-12-23 21:30:07'],
            ['id' => 17, 'name' => 'JIANKI', 'created_at' => '2024-12-23 21:50:49', 'updated_at' => '2024-12-23 21:50:49'],
            ['id' => 18, 'name' => 'ALEMANIA', 'created_at' => '2024-12-30 21:25:08', 'updated_at' => '2024-12-30 21:25:08'],
            ['id' => 19, 'name' => 'GRECIA', 'created_at' => '2025-01-11 02:18:51', 'updated_at' => '2025-01-11 02:18:51'],
            ['id' => 20, 'name' => 'ARGENTINA', 'created_at' => '2025-01-15 22:46:28', 'updated_at' => '2025-01-15 22:46:28'],
            ['id' => 21, 'name' => 'KOREA', 'created_at' => '2025-01-15 22:56:45', 'updated_at' => '2025-01-15 22:56:45'],
            ['id' => 22, 'name' => 'BRAZIL', 'created_at' => '2025-01-16 23:44:09', 'updated_at' => '2025-01-16 23:44:09'],
            ['id' => 23, 'name' => 'PANAMA', 'created_at' => '2025-01-29 19:56:10', 'updated_at' => '2025-01-29 19:56:10'],
            ['id' => 24, 'name' => 'asia', 'created_at' => '2025-02-01 03:39:11', 'updated_at' => '2025-02-01 03:39:11'],
            ['id' => 25, 'name' => 'REINO UNIDO', 'created_at' => '2025-02-01 07:24:43', 'updated_at' => '2025-02-01 07:24:43'],
            ['id' => 26, 'name' => 'PORTUGAL', 'created_at' => '2025-02-09 19:02:02', 'updated_at' => '2025-02-09 19:02:02'],
            ['id' => 27, 'name' => 'PERUS', 'created_at' => '2025-02-10 21:58:30', 'updated_at' => '2025-02-10 21:58:30'],
            ['id' => 28, 'name' => 'TAILANDIA', 'created_at' => '2025-02-10 23:58:37', 'updated_at' => '2025-02-10 23:58:37'],
            ['id' => 29, 'name' => 'MALASIA', 'created_at' => '2025-02-11 01:51:18', 'updated_at' => '2025-02-11 01:51:18'],
            ['id' => 30, 'name' => 'REPUBLICA DOMINICANA', 'created_at' => '2025-02-11 04:21:18', 'updated_at' => '2025-02-11 04:21:18'],
            ['id' => 31, 'name' => 'ESPAÑA', 'created_at' => '2025-02-12 01:32:39', 'updated_at' => '2025-02-12 01:32:39'],
            ['id' => 32, 'name' => 'URUGUAY', 'created_at' => '2025-02-12 02:41:08', 'updated_at' => '2025-02-12 02:41:08'],
            ['id' => 33, 'name' => 'MIAMI', 'created_at' => '2025-02-12 17:35:18', 'updated_at' => '2025-02-12 17:35:18'],
            ['id' => 34, 'name' => 'guatemala', 'created_at' => '2025-02-12 19:49:02', 'updated_at' => '2025-02-12 19:49:02'],
            ['id' => 35, 'name' => 'PERU', 'created_at' => '2025-02-14 01:45:36', 'updated_at' => '2025-02-14 01:45:36'],
            ['id' => 36, 'name' => 'USA', 'created_at' => '2025-02-15 20:01:47', 'updated_at' => '2025-02-15 20:01:47'],
            ['id' => 37, 'name' => 'UCRANIA', 'created_at' => '2025-02-15 22:51:15', 'updated_at' => '2025-02-15 22:51:15'],
            ['id' => 38, 'name' => 'TURKA', 'created_at' => '2025-02-17 23:40:25', 'updated_at' => '2025-02-17 23:40:25'],
            ['id' => 39, 'name' => 'PARAGUAY', 'created_at' => '2025-02-18 21:42:59', 'updated_at' => '2025-02-18 21:42:59'],
            ['id' => 40, 'name' => 'BELGICA', 'created_at' => '2025-05-16 19:11:36', 'updated_at' => '2025-05-16 19:11:36'],
        ];

        Origin::insert($origins);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
