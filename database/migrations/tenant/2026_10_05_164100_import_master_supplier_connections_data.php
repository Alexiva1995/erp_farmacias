<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para sembrar conexiones maestras de proveedores en el tenant.
     */
    public function up(): void
    {
        $sqlPath = database_path('seeders/sql/supplier_connections.sql');
        if (!file_exists($sqlPath)) {
            return;
        }

        // Si ya existen registros, no duplicar
        if (Schema::hasTable('supplier_connections') && DB::table('supplier_connections')->count() > 0) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        $sqlContent = file_get_contents($sqlPath);
        DB::unprepared($sqlContent);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
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
