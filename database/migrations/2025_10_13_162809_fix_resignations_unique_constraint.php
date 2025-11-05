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
        // Eliminar la constraint única actual
        Schema::table('resignations', function (Blueprint $table) {
            $table->dropUnique('resignations_employee_id_unique');
        });

        // Crear un índice único compuesto que considere el soft delete
        // Solo será único cuando deleted_at es NULL
        Schema::table('resignations', function (Blueprint $table) {
            $table->unique(['employee_id', 'deleted_at'], 'resignations_employee_id_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la nueva constraint
        Schema::table('resignations', function (Blueprint $table) {
            $table->dropUnique('resignations_employee_id_deleted_at_unique');
        });

        // Restaurar la constraint original
        Schema::table('resignations', function (Blueprint $table) {
            $table->unique('employee_id', 'resignations_employee_id_unique');
        });
    }
};
