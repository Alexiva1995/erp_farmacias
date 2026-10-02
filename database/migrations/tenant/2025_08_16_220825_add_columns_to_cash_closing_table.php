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
            $table->decimal('bs_card_payment_credit', 15, 2)->default(0.00)->after('bs_delivered');
            $table->decimal('bs_cash_payment_credit', 15, 2)->default(0.00)->after('bs_card_payment_credit');
            $table->decimal('bs_transfer_payment_credit', 15, 2)->default(0.00)->after('bs_cash_payment_credit');
            $table->decimal('bs_mobile_payment_credit', 15, 2)->default(0.00)->after('bs_transfer_payment_credit');

            $table->decimal('cop_cash_payment_credit', 15, 2)->default(0.00)->after('bs_mobile_payment_credit');
            $table->decimal('cop_transfer_payment_credit', 15, 2)->default(0.00)->after('cop_cash_payment_credit');
            $table->decimal('cop_conversion_payment_credit', 15, 2)->default(0.00)->after('cop_transfer_payment_credit');

            $table->decimal('usd_transfer_payment_credit', 15, 2)->default(0.00)->after('cop_conversion_payment_credit');
            $table->decimal('usd_cash_payment_credit', 15, 2)->default(0.00)->after('usd_transfer_payment_credit');
            $table->decimal('usd_paypal_payment_credit', 15, 2)->default(0.00)->after('usd_cash_payment_credit');
            $table->decimal('usd_binance_payment_credit', 15, 2)->default(0.00)->after('usd_paypal_payment_credit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
             $table->dropColumn([
                'bs_card_payment_credit',
                'bs_cash_payment_credit',
                'bs_transfer_payment_credit',
                'bs_mobile_payment_credit',
                'cop_cash_payment_credit',
                'cop_transfer_payment_credit',
                'cop_conversion_payment_credit',
                'usd_transfer_payment_credit',
                'usd_cash_payment_credit',
                'usd_paypal_payment_credit',
                'usd_binance_payment_credit',
            ]);
        });
    }
};
