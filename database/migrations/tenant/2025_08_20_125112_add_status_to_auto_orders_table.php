<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.autoor
     */
    public function up(): void
    {
        Schema::table("auto_orders", function (Blueprint $table) {
            $table->boolean("status")->default(false)->after("total_amount");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("auto_orders", function (Blueprint $table) {
            $table->dropColumn("status");
        });
    }
};
