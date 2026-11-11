<?php

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
        if (Schema::hasTable('dining_zones')) {
            Schema::table('dining_zones', function (Blueprint $table) {
                if (!Schema::hasColumn('dining_zones', 'width_meters')) {
                    $table->decimal('width_meters', 8, 2)->default(12.00)->after('background_style');
                }
                if (!Schema::hasColumn('dining_zones', 'height_meters')) {
                    $table->decimal('height_meters', 8, 2)->default(8.00)->after('width_meters');
                }
                if (!Schema::hasColumn('dining_zones', 'layout_config')) {
                    $table->json('layout_config')->nullable()->after('height_meters');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dining_zones')) {
            Schema::table('dining_zones', function (Blueprint $table) {
                if (Schema::hasColumn('dining_zones', 'layout_config')) {
                    $table->dropColumn('layout_config');
                }
                if (Schema::hasColumn('dining_zones', 'height_meters')) {
                    $table->dropColumn('height_meters');
                }
                if (Schema::hasColumn('dining_zones', 'width_meters')) {
                    $table->dropColumn('width_meters');
                }
            });
        }
    }
};
