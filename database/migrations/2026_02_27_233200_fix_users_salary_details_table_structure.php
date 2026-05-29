<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Asegurar AUTO_INCREMENT en users_salary_details
        // Nota: El campo ya es PRIMARY KEY según SHOW INDEX
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users_salary_details MODIFY id BIGINT UNSIGNED AUTO_INCREMENT');
        }

        // 2. Añadir claves foráneas (Comentado porque ya existen desde la migración original)
        /*
        Schema::table('users_salary_details', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('salary_concept_id')->references('id')->on('salary_concepts')->onDelete('cascade');
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        Schema::table('users_salary_details', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['salary_concept_id']);
        });
        */

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users_salary_details MODIFY id BIGINT UNSIGNED');
        }
    }
};
