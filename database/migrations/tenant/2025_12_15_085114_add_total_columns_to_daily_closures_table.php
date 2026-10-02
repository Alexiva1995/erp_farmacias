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
        Schema::table('daily_closures', function (Blueprint $table) {
            $table->decimal('total_credits', 15, 2)->default(0.00)->after('bs_delivered');
            $table->decimal('total_payment_credit', 15, 2)->default(0.00)->after('total_credits');
            $table->decimal('total_delivery', 15, 2)->default(0.00)->after('total_payment_credit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_closures', function (Blueprint $table) {
            $table->dropColumn('total_credits');
            $table->dropColumn('total_payment_credit');
            $table->dropColumn('total_delivery');
        });
    }
};
