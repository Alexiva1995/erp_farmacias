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
        Schema::table('order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_details', 'unit_price_usd')) {
                $table->decimal('unit_price_usd', 12, 2)->default(0.00)->after('unit_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
             if (Schema::hasColumn('order_details', 'unit_price_usd')) {
                $table->dropColumn('unit_price_usd', 12, 2);
            }
        });
    }
};
