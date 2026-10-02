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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            $table->decimal('credit_amount', 12, 2);
            $table->decimal('pending_amount', 12, 2);
            $table->dateTime('credit_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->enum('status', ['Active', 'Paid'])->default('Active');

            $table->timestamps();

            $table->index('client_id', 'idx_credit_client');
            $table->index('order_id', 'order_id');
            $table->index('status', 'idx_credit_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
