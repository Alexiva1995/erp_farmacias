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
        Schema::create('supplier_scores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->decimal('score', 5, 2); // Calificación ejemplo: 87.45%
            $table->json('breakdown')->nullable(); // Detalle de los factores
            $table->date('evaluated_on'); // Fecha de cálculo de la calificación
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('evaluated_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_scores');
    }
};