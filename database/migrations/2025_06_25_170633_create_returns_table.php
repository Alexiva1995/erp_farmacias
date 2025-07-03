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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('amount_refunded', 10, 2);
            $table->dateTime('return_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->enum('status', ['Active', 'Paid'])->default('Active');
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('return_date', 'idx_return_date');
            $table->index('status', 'idx_return_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
