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
        Schema::create('cash_closing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->date('closing_date')->index('idx_closing_date');
            $table->enum('status', ['open', 'closed'])->index('idx_closing_status');

            $table->decimal('total_usd', 15, 2)->default(0.00);
            $table->decimal('total_cop', 15, 2)->default(0.00);
            $table->decimal('total_bs', 15, 2)->default(0.00);

            $table->decimal('bs_card', 15, 2)->default(0.00);
            $table->decimal('bs_cash', 15, 2)->default(0.00);
            $table->decimal('bs_transfer', 15, 2)->default(0.00);
            $table->decimal('bs_mobile', 15, 2)->default(0.00);

            $table->decimal('cop_cash', 15, 2)->default(0.00);
            $table->decimal('cop_transfer', 15, 2)->default(0.00);
            $table->decimal('cop_conversion', 15, 2)->default(0.00);
            $table->decimal('cop_spare', 15, 2)->default(0.00);

            $table->decimal('usd_transfer', 15, 2)->default(0.00);
            $table->decimal('usd_cash', 15, 2)->default(0.00);
            $table->decimal('usd_paypal', 15, 2)->default(0.00);
            $table->decimal('usd_binance', 15, 2)->default(0.00);
            $table->decimal('usd_conversion', 15, 2)->default(0.00);
            $table->decimal('usd_credit', 15, 2)->default(0.00);

            $table->decimal('usd_delivered', 15, 2)->default(0.00);
            $table->decimal('cop_delivered', 15, 2)->default(0.00);
            $table->decimal('bs_delivered', 15, 2)->default(0.00);

            $table->timestamps();
            $table->index('seller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_closing');
    }
};
