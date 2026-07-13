<?php

use App\AutoOrderDetailStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("auto_order_details", function (Blueprint $table) {
            $table->tinyInteger("status")->default(AutoOrderDetailStatus::PENDING->value)->after("subtotal");
            $table->decimal("final_cost", 10, 2)->after("status");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("auto_order_details", function (Blueprint $table) {
            $table->dropColumn("status");
            $table->dropColumn("final_cost");
        });
    }
};
