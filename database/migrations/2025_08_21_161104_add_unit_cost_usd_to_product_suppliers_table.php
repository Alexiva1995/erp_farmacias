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
            $table->decimal("unit_cost_usd", 10, 2)->after("unit_cost");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("product_suppliers", function (Blueprint $table) {
            $table->dropColumn("unit_cost_usd");
        });
    }
};
