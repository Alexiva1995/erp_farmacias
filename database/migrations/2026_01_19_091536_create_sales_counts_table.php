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
        Schema::create('sales_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('inventory_cycles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('counted_quantity');
            $table->integer('system_quantity');
            $table->integer('discrepancy')->comment('counted_quantity - system_quantity');
            $table->enum('type', ['sale']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'recount'])->default('pending');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_counts');
    }
};
