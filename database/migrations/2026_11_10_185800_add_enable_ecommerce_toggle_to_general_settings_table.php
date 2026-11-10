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
        if (!Schema::hasColumn('general_settings', 'enable_ecommerce_toggle')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->boolean('enable_ecommerce_toggle')->default(true)->after('enable_bulk_toggle_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('general_settings', 'enable_ecommerce_toggle')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('enable_ecommerce_toggle');
            });
        }
    }
};
