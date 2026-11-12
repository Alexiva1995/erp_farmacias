<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('general_settings', 'kds_multi_station')) {
                    $table->boolean('kds_multi_station')->default(true);
                }
                if (!Schema::hasColumn('general_settings', 'kds_courses')) {
                    $table->json('kds_courses')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (Schema::hasColumn('general_settings', 'kds_multi_station')) {
                    $table->dropColumn('kds_multi_station');
                }
                if (Schema::hasColumn('general_settings', 'kds_courses')) {
                    $table->dropColumn('kds_courses');
                }
            });
        }
    }
};
