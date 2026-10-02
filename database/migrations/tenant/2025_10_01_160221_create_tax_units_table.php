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
        Schema::create('tax_units', function (Blueprint $table) {
            $table->id();
            $table->decimal('value', 10, 2); // Valor de la unidad tributaria
            $table->date('effective_date'); // Fecha desde la cual es válido este valor
            $table->boolean('is_active')->default(true); // Si es el valor actual
            $table->text('notes')->nullable(); // Notas opcionales
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_units');
    }
};
