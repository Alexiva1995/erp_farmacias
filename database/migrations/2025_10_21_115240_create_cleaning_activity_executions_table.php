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
        Schema::create('cleaning_activity_executions', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade')
                ->comment('Empleado asignado a la actividad');

            $table->foreignId('cleaning_activity_id')
                ->constrained('cleaning_activities')
                ->onDelete('cascade')
                ->comment('Actividad de limpieza a realizar');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Usuario supervisor que aprobó la actividad');

            // Fechas
            $table->date('scheduled_date')->comment('Fecha de inicio del período de la actividad');
            $table->date('due_date')->comment('Fecha límite para completar según frecuencia');
            $table->dateTime('completed_date')->nullable()->comment('Fecha y hora real de completado');
            $table->dateTime('approved_date')->nullable()->comment('Fecha y hora de aprobación por supervisor');

            // Estado y evidencia
            $table->enum('status', ['Pendiente', 'Procesada', 'Completada', 'Vencida', 'Cancelada'])
                ->default('Pendiente')
                ->comment('Estado actual: Pendiente (por hacer), Procesada (esperando aprobación), Completada (aprobada), Vencida (expirada), Cancelada');

            $table->string('photo')->nullable()->comment('Ruta de la foto de evidencia');

            // Notas adicionales
            $table->text('notes')->nullable()->comment('Observaciones o comentarios del empleado');
            $table->text('rejection_reason')->nullable()->comment('Razón de rechazo si el supervisor no aprueba');

            $table->timestamps();

            // Índices optimizados
            $table->index(['employee_id', 'due_date'], 'cae_emp_due_idx');
            $table->index(['cleaning_activity_id', 'due_date'], 'cae_act_due_idx');
            $table->index(['status', 'due_date'], 'cae_status_due_idx');
            $table->index('due_date', 'cae_due_idx');
            $table->index('scheduled_date', 'cae_scheduled_idx');
            $table->index('approved_by', 'cae_approved_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cleaning_activity_executions');
    }
};
