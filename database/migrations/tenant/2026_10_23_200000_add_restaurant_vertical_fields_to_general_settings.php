<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'enable_tables')) {
                $table->boolean('enable_tables')->default(false)->after('enable_dishes');
            }
            if (!Schema::hasColumn('general_settings', 'enable_kds')) {
                $table->boolean('enable_kds')->default(false)->after('enable_tables');
            }
            if (!Schema::hasColumn('general_settings', 'enable_modifiers')) {
                $table->boolean('enable_modifiers')->default(false)->after('enable_kds');
            }
            if (!Schema::hasColumn('general_settings', 'enable_split_check')) {
                $table->boolean('enable_split_check')->default(false)->after('enable_modifiers');
            }
            if (!Schema::hasColumn('general_settings', 'enable_tips')) {
                $table->boolean('enable_tips')->default(false)->after('enable_split_check');
            }
            if (!Schema::hasColumn('general_settings', 'default_service_fee_pct')) {
                $table->decimal('default_service_fee_pct', 5, 2)->default(10.00)->after('enable_tips');
            }
            if (!Schema::hasColumn('general_settings', 'enable_sub_recipes')) {
                $table->boolean('enable_sub_recipes')->default(false)->after('default_service_fee_pct');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'enable_tables',
                'enable_kds',
                'enable_modifiers',
                'enable_split_check',
                'enable_tips',
                'default_service_fee_pct',
                'enable_sub_recipes'
            ] as $column) {
                if (Schema::hasColumn('general_settings', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
