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
        Schema::table('cash_closing', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_closing', 'usd_balance')) {
                $table->decimal('usd_balance', 15, 2)->default(0.00)->after('usd_credit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
            if (Schema::hasColumn('cash_closing', 'usd_balance')) {
                $table->dropColumn('usd_balance', 15, 2);
            }
        });
    }
};
