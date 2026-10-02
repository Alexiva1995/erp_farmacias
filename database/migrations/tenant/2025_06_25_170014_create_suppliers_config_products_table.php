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
        Schema::create('suppliers_config_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('barcode', 191)->nullable();
            $table->string('product_name', 191)->nullable();
            $table->string('laboratory', 191)->nullable();
            $table->string('active_ingredient', 191)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('head_row_number')->nullable();
            $table->timestamps();

            $table->index('supplier_id', 'idx_supplier_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers_config_products');
    }
};
