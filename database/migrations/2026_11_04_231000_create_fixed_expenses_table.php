<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de gastos fijos (alquiler, servicios, licencias, etc.).
     */
    public function up(): void
    {
        if (Schema::hasTable('fixed_expenses')) {
            return;
        }

        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            // Monto estimado (luz, agua, gas): se confirma el monto real al pagar
            $table->boolean('is_amount_variable')->default(false);
            // Método de pago sugerido al registrar el pago
            $table->string('default_count', 50)->nullable();
            // Frecuencia: cada N días/semanas/meses/años
            $table->string('interval_unit', 10)->default('month');
            $table->unsignedSmallInteger('interval_count')->default(1);
            // Día ancla del mes (derivado de la primera fecha de pago)
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date');
            // Días de anticipación con los que aparece la cuota por pagar
            $table->unsignedSmallInteger('reminder_days_before')->default(3);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_due_date'], 'idx_fixed_expenses_active_next');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_expenses');
    }
};
