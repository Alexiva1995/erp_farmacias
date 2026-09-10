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
        Schema::table('inventory_snapshots', function (Blueprint $table) {
            $table->integer('expiring_risk_products_count')->default(0)->after('overstock_inventory_value');
            $table->decimal('expiring_risk_inventory_value', 15, 2)->default(0)->after('expiring_risk_products_count');
        });

        Schema::table('inventory_snapshot_items', function (Blueprint $table) {
            $table->decimal('risk_expiring_units', 12, 2)->default(0)->after('days_to_expiration');
            $table->decimal('risk_expiring_value_usd', 15, 2)->default(0)->after('risk_expiring_units');
            $table->boolean('has_expiration_risk')->default(false)->after('risk_expiring_value_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_snapshot_items', function (Blueprint $table) {
            $table->dropColumn(['risk_expiring_units', 'risk_expiring_value_usd', 'has_expiration_risk']);
        });

        Schema::table('inventory_snapshots', function (Blueprint $table) {
            $table->dropColumn(['expiring_risk_products_count', 'expiring_risk_inventory_value']);
        });
    }
};
