<?php

use App\Enums\RoleEnum;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            $hasMenuPermissions = Schema::hasColumn('roles', 'menu_permissions');

            $data = [
                'name' => 'Contador',
                'updated_at' => Carbon::now(),
            ];

            if ($hasMenuPermissions) {
                // Solo establecer por defecto si no ha sido configurado previamente
                $existing = DB::table('roles')->where('id', RoleEnum::CONTADOR->value)->first();
                if (!$existing || empty($existing->menu_permissions)) {
                    $data['menu_permissions'] = json_encode(RoleEnum::defaultFiscalMenuPermissions());
                }
            }

            $existing = DB::table('roles')->where('id', RoleEnum::CONTADOR->value)->first();
            if ($existing) {
                DB::table('roles')->where('id', RoleEnum::CONTADOR->value)->update($data);
            } else {
                $data['id'] = RoleEnum::CONTADOR->value;
                $data['created_at'] = Carbon::now();
                DB::table('roles')->insert($data);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('id', RoleEnum::CONTADOR->value)->delete();
        }
    }
};
