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
        Schema::table('product_suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('product_suppliers', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->default(0)->nullable()->after('unit_cost_usd_with_discount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('product_suppliers', 'discount_percentage')) {
                $table->dropColumn('discount_percentage');
            }
        });
    }
};