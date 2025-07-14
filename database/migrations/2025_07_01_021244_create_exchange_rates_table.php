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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id(); // Equivalente a BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
            $table->string('currency_code', 10);
            $table->decimal('rate', 10, 4);
            $table->string('source', 50)->nullable();
            $table->timestamps(); // Maneja created_at y updated_at con timestamps por defecto

            $table->index('currency_code', 'idx_currency_code');
            $table->index('rate', 'idx_exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
