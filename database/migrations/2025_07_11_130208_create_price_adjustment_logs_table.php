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
        Schema::create('price_adjustment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->foreignId('expired_log_id')->constrained('expired_logs')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('product_name');
            $table->foreignId('lot_id')->nullable()->constrained('product_lots')->onDelete('set null');
            $table->string('lot_number')->nullable();
            $table->decimal('cost_redistributed', 15, 2);
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_adjustment_logs');
    }
};
