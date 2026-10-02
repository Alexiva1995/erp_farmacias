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
        Schema::create('fiscal_contributions', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20)->comment('Periodo fiscal, ej: 09/2026');
            $table->string('tax_type', 50)->comment('Tipo de impuesto: ANTICIPO-ISLR, IGTF, IVA/35, DPP, etc.');
            $table->string('document_number', 50)->index()->comment('Numero de documento / compromiso SENIAT');
            $table->date('operation_date')->comment('Fecha de operacion / emision');
            $table->date('due_date')->comment('Fecha de vencimiento');
            $table->decimal('amount', 15, 2)->default(0)->comment('Monto en Bolivares');
            $table->string('status', 20)->default('pending')->comment('pending, paid, expired');
            $table->date('payment_date')->nullable()->comment('Fecha en que se realizo el pago');
            $table->string('payment_reference', 100)->nullable()->comment('Numero de referencia bancaria');
            $table->string('source', 30)->default('manual')->comment('manual, smart_paste, bot');
            $table->text('notes')->nullable()->comment('Observaciones adicionales');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indice compuesto para evitar duplicados del mismo documento tributario
            $table->unique(['tax_type', 'document_number', 'period'], 'uq_contrib_tax_doc_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_contributions');
    }
};
