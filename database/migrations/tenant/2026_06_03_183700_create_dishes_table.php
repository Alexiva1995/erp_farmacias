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
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('cost_price', 10, 2);
            $table->decimal('cpv', 10, 2)->nullable();
            $table->decimal('suggested_price', 10, 2);
            $table->decimal('designated_price', 10, 2);
            $table->string('percentage_profit'); // Multiplicador de ganancia
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->tinyInteger('status')->default(1)->comment('0 - Inactivo, 1 - Activo, 2 - En Revisión');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
