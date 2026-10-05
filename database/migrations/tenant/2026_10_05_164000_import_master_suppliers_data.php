<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para sembrar proveedores maestros en el tenant.
     */
    public function up(): void
    {
        $sqlPath = database_path('seeders/sql/suppliers.sql');
        if (!file_exists($sqlPath)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        $sqlContent = file_get_contents($sqlPath);

        // Si la tabla no existe físicamente (ej. fue borrada a mano), ejecutar el dump completo con CREATE TABLE
        if (!Schema::hasTable('suppliers')) {
            DB::unprepared($sqlContent);
            Schema::enableForeignKeyConstraints();
            return;
        }

        // Si la tabla existe pero está vacía, insertar solo la data
        if (DB::table('suppliers')->count() === 0) {
            if (preg_match('/INSERT INTO `suppliers`\s*\((.*?)\)\s*VALUES\s*(.*?);/s', $sqlContent, $matches)) {
                DB::unprepared($matches[0]);
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        // No destructivo por defecto
    }
};
