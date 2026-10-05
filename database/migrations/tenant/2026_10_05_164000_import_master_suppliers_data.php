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

        // Si la tabla ya tiene datos, no sobreescribir automáticamente
        if (Schema::hasTable('suppliers') && DB::table('suppliers')->count() > 0) {
            return;
        }

        $sqlContent = file_get_contents($sqlPath);

        if (preg_match('/INSERT INTO `suppliers`\s*\((.*?)\)\s*VALUES\s*(.*?);/s', $sqlContent, $matches)) {
            Schema::disableForeignKeyConstraints();
            DB::unprepared($matches[0]);
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        // No destructivo por defecto
    }
};
