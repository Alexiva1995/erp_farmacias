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
            $table->decimal('diff_cop', 15, 2)->default(0.00)->after('blind_mismatches');
            $table->decimal('diff_cop_transfer', 15, 2)->default(0.00)->after('diff_cop');
            $table->decimal('diff_usd', 15, 2)->default(0.00)->after('diff_cop_transfer');
            $table->decimal('diff_credit', 15, 2)->default(0.00)->after('diff_usd');
            $table->decimal('diff_bs_mobile', 15, 2)->default(0.00)->after('diff_credit');
            $table->decimal('diff_bs_card', 15, 2)->default(0.00)->after('diff_bs_mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
            $table->dropColumn([
                'diff_cop',
                'diff_cop_transfer',
                'diff_usd',
                'diff_credit',
                'diff_bs_mobile',
                'diff_bs_card'
            ]);
        });
    }
};
