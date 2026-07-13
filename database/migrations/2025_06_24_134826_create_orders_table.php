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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cash_closing_id')->constrained('cash_closing')->onDelete('cascade');

            $table->decimal('total_amount', 12, 2);
            $table->decimal('money_returns', 12, 2);
            $table->enum('currency', ['Bs', 'USD', 'COP']);
            $table->dateTime('order_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->enum('status', ['Completed', 'Cancelled', 'Abandoned', 'Pending', 'closed'])->default('Pending');
            $table->boolean('has_multiple_currencies')->nullable()->default(false);
            $table->json('payment_methods');

            $table->timestamps();

            $table->index('seller_id');
            $table->index('cash_closing_id');
            $table->index('order_date', 'idx_order_date');
            $table->index('status', 'idx_order_status');
            $table->index('client_id', 'idx_order_client');
            $table->index(['order_date', 'status'], 'idx_order_date_status');
            $table->index(['client_id', 'currency'], 'idx_order_client_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
