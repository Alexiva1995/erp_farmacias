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
        if (!Schema::hasColumn('general_settings', 'tertiary_color')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->string('tertiary_color')->nullable()->default('#F5C842')->after('secondary_color');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('general_settings', 'tertiary_color')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('tertiary_color');
            });
        }
    }
};
