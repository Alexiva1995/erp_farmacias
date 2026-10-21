<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Admin', 'menu_permissions' => null],
            ['id' => 2, 'name' => 'Supervisor', 'menu_permissions' => null],
            ['id' => 3, 'name' => 'Employee', 'menu_permissions' => null],
            ['id' => 4, 'name' => 'Administrador', 'menu_permissions' => null],
            ['id' => 5, 'name' => 'Contador', 'menu_permissions' => json_encode(\App\Enums\RoleEnum::defaultFiscalMenuPermissions())],
        ];

        foreach ($roles as $role) {
            $updateData = [
                'name' => $role['name'],
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('roles', 'menu_permissions')) {
                $updateData['menu_permissions'] = $role['menu_permissions'];
            }

            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                $updateData
            );
        }
    }
}
