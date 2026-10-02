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
        Schema::create('resignations', function (Blueprint $table) {
            $table->id(); // bigint(20) unsigned, auto_increment, PK
            
            // Relación con employees (FK)
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees');
            
            // Datos del empleado (redundantes para consultas rápidas)
            $table->string('employee_name'); // name + last_name concatenado
            $table->string('employee_identification'); // employees.identification
            $table->string('employee_email')->nullable(); // Se obtendrá de users.email
            
            // Campo que NO existe en employees
            $table->string('employee_position')->nullable(); // Nuevo campo
            
            // Fechas de la renuncia
            $table->date('start_date'); // employees.created_at (fecha de inicio)
            $table->enum('resignation_type', ['voluntary', 'unjustified_dismissal']);
            $table->date('request_date'); // Fecha de solicitud
            $table->date('effective_date'); // Fecha efectiva de renuncia
            
            // Estado del empleado (sincronizado con employees.is_active)
            $table->string('employee_status')->default('Activo'); // 'Activo' o 'Inactivo'
            
            // Timestamps estándar
            $table->timestamps();
            
            // Constraints e índices
            $table->unique('employee_id'); // Prevenir duplicados
            $table->index(['employee_id', 'resignation_type']);
            $table->index(['effective_date']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resignations');
    }
};
