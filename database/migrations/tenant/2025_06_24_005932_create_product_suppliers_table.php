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
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');

            $table->string('barcode_match')->nullable();
            $table->string('name')->nullable();
            $table->string('laboratory')->nullable();
            $table->date('expiration')->nullable();
            $table->decimal('unit_cost', 10, 2);
            $table->date('connection_date');

            $table->timestamps();

            $table->unique(['product_id', 'supplier_id'], 'uniq_product_supplier');
            $table->index(['supplier_id', 'product_id'], 'idx_supplier_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
