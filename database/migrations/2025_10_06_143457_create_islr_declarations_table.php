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
        Schema::create('islr_declarations', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique(); // Solo una declaración por año
            $table->decimal('amount', 15, 2); // Monto a pagar
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid'); // Estado de pago
            $table->date('declaration_date'); // Fecha de la declaración
            $table->timestamps();

            // Índices
            $table->index('year');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('islr_declarations');
    }
};
