<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('lot_number')->nullable();
            $table->date('expiration_date');
            $table->integer('quantity');
            $table->string('location')->nullable();
            $table->decimal('cost_price', 10, 2);

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_lots');
    }
};
