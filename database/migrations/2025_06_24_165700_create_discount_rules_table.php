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
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_laboratory_id')->constrained('supplier_laboratories')->onDelete('cascade');

            $table->decimal('min_amount', 12, 2)->nullable();
            $table->integer('min_quantity')->nullable();
            $table->decimal('discount_percentage', 5, 2);

            $table->timestamps();

            $table->index('supplier_laboratory_id', 'supplier_laboratory_id');
            $table->index('min_amount', 'idx_discount_min_amount');
            $table->index('min_quantity', 'idx_discount_min_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
