<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'menu_permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->json('menu_permissions')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'menu_permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('menu_permissions');
            });
        }
    }
};
