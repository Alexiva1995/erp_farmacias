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
        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'enable_custom_change_currency')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->boolean('enable_custom_change_currency')->default(false)->after('round_usd_up');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'enable_custom_change_currency')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('enable_custom_change_currency');
            });
        }
    }
};
