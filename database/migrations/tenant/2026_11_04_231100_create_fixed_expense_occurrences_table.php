<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuotas por pagar generadas a partir de cada gasto fijo.
     */
    public function up(): void
    {
        if (Schema::hasTable('fixed_expense_occurrences')) {
            return;
        }

        Schema::create('fixed_expense_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_expense_id')->constrained('fixed_expenses')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            // Pending (por pagar), Paid (pago registrado), Skipped (omitida)
            $table->string('status', 20)->default('Pending');
            // Gasto real creado al registrar el pago
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('skipped_at')->nullable();
            $table->foreignId('skipped_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // Idempotencia: una sola cuota por gasto fijo y fecha
            $table->unique(['fixed_expense_id', 'due_date'], 'uq_fixed_expense_occurrence_due');
            $table->index(['status', 'due_date'], 'idx_fixed_occurrences_status_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_expense_occurrences');
    }
};
