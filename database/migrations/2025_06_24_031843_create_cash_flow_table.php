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
        Schema::create('cash_flow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_closing_id')->constrained('cash_closing')->onDelete('cascade');
            $table->date('flow_date')->index('idx_cash_flow_date');

            $table->decimal('amount_usd', 15, 2)->default(0.00);
            $table->decimal('amount_binance', 15, 2)->default(0.00);
            $table->decimal('amount_paypal', 15, 2)->default(0.00);
            $table->decimal('amount_credit_pending', 15, 2)->default(0.00);
            $table->decimal('amount_cop', 15, 2)->default(0.00);
            $table->decimal('amount_bancolombia', 15, 2)->default(0.00);
            $table->decimal('amount_bs_mobile', 15, 2)->default(0.00);
            $table->decimal('amount_bs_transfer', 15, 2)->default(0.00);
            $table->decimal('amount_bs_card', 15, 2)->default(0.00);
            $table->decimal('amount_bs_cash', 15, 2)->default(0.00);

            $table->timestamps();

            $table->index('cash_closing_id', 'cash_closing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flow');
    }
};
