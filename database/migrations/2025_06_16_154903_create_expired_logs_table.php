<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expired_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('product_lots')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('product_name');
            $table->string('lot_number');
            $table->integer('expired_quantity');
            $table->decimal('cost_per_unit', 10, 2);
            $table->decimal('total_lost_value', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_logs');
    }
};
