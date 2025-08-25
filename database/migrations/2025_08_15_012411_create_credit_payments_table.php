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
        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cash_closing_id')->constrained('cash_closing')->onDelete('cascade');
            $table->decimal('money_returns', 12, 2);
             $table->json('method_Payment')
                ->comment('metodo de pago del usuario para el abono del crédito')
                ->nullable();
            $table->timestamp('payment_date');
            $table->timestamps();

            $table->index('seller_id', 'seller_id');
            $table->index('cash_closing_id', 'cash_closing_id');
            $table->index('client_id', 'idx_order_client');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
    }
};
