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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'blind_cash_closure')) {
                $table->boolean('blind_cash_closure')->default(false);
            }
        });

        Schema::table('cash_closing', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_closing', 'declared_cop')) {
                $table->decimal('declared_cop', 15, 2)->nullable();
                $table->decimal('declared_usd', 15, 2)->nullable();
                $table->decimal('declared_credit', 15, 2)->nullable();
                $table->decimal('declared_bs_mobile', 15, 2)->nullable();
                $table->decimal('declared_bs_card', 15, 2)->nullable();
                $table->text('blind_mismatches')->nullable();
                $table->text('blind_note')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'blind_cash_closure')) {
                $table->dropColumn('blind_cash_closure');
            }
        });

        Schema::table('cash_closing', function (Blueprint $table) {
            if (Schema::hasColumn('cash_closing', 'declared_cop')) {
                $table->dropColumn([
                    'declared_cop',
                    'declared_usd',
                    'declared_credit',
                    'declared_bs_mobile',
                    'declared_bs_card',
                    'blind_mismatches',
                    'blind_note'
                ]);
            }
        });
    }
};
