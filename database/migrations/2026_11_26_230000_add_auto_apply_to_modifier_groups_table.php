<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modifier_groups') && !Schema::hasColumn('modifier_groups', 'auto_apply_by_base_product')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->boolean('auto_apply_by_base_product')->default(true)->after('base_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modifier_groups') && Schema::hasColumn('modifier_groups', 'auto_apply_by_base_product')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->dropColumn('auto_apply_by_base_product');
            });
        }
    }
};
