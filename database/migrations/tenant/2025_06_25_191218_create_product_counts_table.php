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
        Schema::create('product_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('inventory_cycles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_lot_id')->nullable()->constrained('product_lots')->nullOnDelete();
            $table->integer('counted_quantity');
            $table->integer('system_quantity');
            $table->integer('discrepancy')->comment('counted_quantity - system_quantity');
            $table->enum('status', ['pending', 'approved', 'rejected', 'recount'])->default('pending');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('product_lot_id');
            $table->index('supervisor_id');
            $table->index('user_id');
            $table->index('status', 'idx_count_status');
            $table->index('cycle_id', 'idx_count_cycle');
            $table->index('product_id', 'idx_count_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_counts');
    }
};
