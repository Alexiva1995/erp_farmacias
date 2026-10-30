<?php

declare(strict_types=1);

use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea los roles operativos de la vertical Droguería B2B con sus permisos de menú por defecto.
     */
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $hasMenuPermissions = Schema::hasColumn('roles', 'menu_permissions');
        $drogueriaRoles = RoleEnum::defaultDrogueriaMenuPermissions();

        foreach ($drogueriaRoles as $roleName => $defaultPermissions) {
            $existing = DB::table('roles')->where('name', $roleName)->first();

            if (!$existing) {
                $payload = [
                    'name'       => $roleName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasMenuPermissions) {
                    $payload['menu_permissions'] = json_encode($defaultPermissions);
                }

                DB::table('roles')->insert($payload);
            } elseif ($hasMenuPermissions && empty($existing->menu_permissions)) {
                DB::table('roles')
                    ->where('id', $existing->id)
                    ->update([
                        'menu_permissions' => json_encode($defaultPermissions),
                        'updated_at'       => now(),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->whereIn('name', array_keys(RoleEnum::defaultDrogueriaMenuPermissions()))
                ->delete();
        }
    }
};
