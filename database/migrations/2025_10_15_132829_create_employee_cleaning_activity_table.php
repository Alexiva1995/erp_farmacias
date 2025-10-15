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
        Schema::create('employee_cleaning_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('cleaning_activity_id')
                ->constrained('cleaning_activities')
                ->onDelete('cascade');
            $table->enum('status', ['Pendiente', 'Completada', 'Cancelada'])
                ->default('Pendiente');
            $table->date('assigned_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['employee_id', 'cleaning_activity_id'], 'employee_cleaning_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_cleaning_activity');
    }
};
