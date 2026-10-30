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
        $drogueriaPermissions = \App\Enums\RoleEnum::defaultDrogueriaMenuPermissions();

        $roles = [
            ['id' => 1, 'name' => 'Admin', 'menu_permissions' => null],
            ['id' => 2, 'name' => 'Supervisor', 'menu_permissions' => null],
            ['id' => 3, 'name' => 'Employee', 'menu_permissions' => null],
            ['id' => 4, 'name' => 'Administrador', 'menu_permissions' => null],
            ['id' => 5, 'name' => 'Contador', 'menu_permissions' => json_encode(\App\Enums\RoleEnum::defaultFiscalMenuPermissions())],
            ['id' => 6, 'name' => \App\Enums\RoleEnum::ASESOR_COMERCIAL->label(), 'menu_permissions' => json_encode($drogueriaPermissions[\App\Enums\RoleEnum::ASESOR_COMERCIAL->label()])],
            ['id' => 7, 'name' => \App\Enums\RoleEnum::TELEMARKETING->label(), 'menu_permissions' => json_encode($drogueriaPermissions[\App\Enums\RoleEnum::TELEMARKETING->label()])],
            ['id' => 8, 'name' => \App\Enums\RoleEnum::COBRANZAS->label(), 'menu_permissions' => json_encode($drogueriaPermissions[\App\Enums\RoleEnum::COBRANZAS->label()])],
            ['id' => 9, 'name' => \App\Enums\RoleEnum::ALMACEN_DESPACHO->label(), 'menu_permissions' => json_encode($drogueriaPermissions[\App\Enums\RoleEnum::ALMACEN_DESPACHO->label()])],
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
