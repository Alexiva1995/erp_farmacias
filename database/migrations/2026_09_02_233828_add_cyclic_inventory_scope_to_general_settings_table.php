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
        Schema::table('general_settings', function (Blueprint $table) {
            $table->string('cyclic_inventory_scope', 20)->default('all')->after('cyclic_inventory_barcode_required');
            $table->unsignedInteger('cyclic_inventory_daily_quota')->default(50)->after('cyclic_inventory_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn(['cyclic_inventory_scope', 'cyclic_inventory_daily_quota']);
        });
    }
};
