<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Laboratory;

class LaboratoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Laboratory::truncate();

        $laboratories = [
            ['id' => 1, 'name' => 'PORTUGAL', 'group_id' => null, 'created_at' => '2024-12-20 23:56:29', 'updated_at' => '2024-12-20 23:56:29'],
            ['id' => 2, 'name' => 'VALMORCA', 'group_id' => null, 'created_at' => '2024-12-21 16:23:14', 'updated_at' => '2024-12-21 16:23:14'],
            ['id' => 3, 'name' => 'MEDIGEN', 'group_id' => null, 'created_at' => '2024-12-21 16:37:09', 'updated_at' => '2024-12-21 16:37:09'],
            ['id' => 4, 'name' => 'BIOFARCO', 'group_id' => null, 'created_at' => '2024-12-21 16:46:11', 'updated_at' => '2024-12-21 16:46:11'],
            ['id' => 5, 'name' => 'PLUSANDEX', 'group_id' => null, 'created_at' => '2024-12-21 17:09:11', 'updated_at' => '2024-12-21 17:09:11'],
            ['id' => 6, 'name' => 'MEYER', 'group_id' => null, 'created_at' => '2024-12-21 17:14:42', 'updated_at' => '2024-12-21 17:14:42'],
            ['id' => 7, 'name' => 'MCK', 'group_id' => null, 'created_at' => '2024-12-21 17:19:12', 'updated_at' => '2024-12-21 17:19:12'],
            ['id' => 8, 'name' => 'FARMA', 'group_id' => null, 'created_at' => '2024-12-21 18:11:10', 'updated_at' => '2024-12-21 18:11:10'],
            ['id' => 9, 'name' => 'GENCER', 'group_id' => null, 'created_at' => '2024-12-21 18:57:29', 'updated_at' => '2024-12-21 18:57:29'],
            ['id' => 10, 'name' => 'OFTALMI', 'group_id' => null, 'created_at' => '2024-12-21 19:03:28', 'updated_at' => '2024-12-21 19:03:28'],
            ['id' => 11, 'name' => 'ZAKI', 'group_id' => null, 'created_at' => '2024-12-21 19:31:04', 'updated_at' => '2024-12-21 19:31:04'],
            ['id' => 12, 'name' => 'COFASA', 'group_id' => null, 'created_at' => '2024-12-21 20:04:07', 'updated_at' => '2024-12-21 20:04:07'],
            ['id' => 13, 'name' => 'VIVAX', 'group_id' => null, 'created_at' => '2024-12-21 20:16:01', 'updated_at' => '2024-12-21 20:16:01'],
            ['id' => 14, 'name' => 'ELMOR', 'group_id' => null, 'created_at' => '2024-12-21 21:05:00', 'updated_at' => '2024-12-21 21:05:00'],
            ['id' => 15, 'name' => 'VICTUS', 'group_id' => null, 'created_at' => '2024-12-23 18:25:03', 'updated_at' => '2024-12-23 18:25:03'],
            ['id' => 16, 'name' => 'GARDEN HOUSE', 'group_id' => null, 'created_at' => '2024-12-23 18:33:24', 'updated_at' => '2024-12-23 18:33:24'],
            ['id' => 17, 'name' => 'BIONECTAR', 'group_id' => null, 'created_at' => '2024-12-23 18:43:49', 'updated_at' => '2024-12-23 18:43:49'],
            ['id' => 18, 'name' => 'biofarco', 'group_id' => null, 'created_at' => '2024-12-23 18:54:57', 'updated_at' => '2024-12-23 18:54:57'],
            ['id' => 19, 'name' => 'BIOFARCO', 'group_id' => null, 'created_at' => '2024-12-23 18:55:11', 'updated_at' => '2024-12-23 18:55:11'],
            ['id' => 20, 'name' => 'DPT', 'group_id' => null, 'created_at' => '2024-12-23 19:17:42', 'updated_at' => '2024-12-23 19:17:42'],
        ];

        Laboratory::insert($laboratories);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
