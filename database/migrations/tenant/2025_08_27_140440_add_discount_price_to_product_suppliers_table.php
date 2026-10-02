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
        Schema::table("product_suppliers", function (Blueprint $table) {
            $table->decimal("unit_cost_with_discount", 10, 2)->nullable()->after("connection_date");
            $table->decimal("unit_cost_usd_with_discount", 10, 2)->nullable()->after("unit_cost_with_discount");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("product_suppliers", function (Blueprint $table) {
            $table->dropColumn("unit_cost_with_discount");
            $table->dropColumn("unit_cost_usd_with_discount");
        });
    }
};
