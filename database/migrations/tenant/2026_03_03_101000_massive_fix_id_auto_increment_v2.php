<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Consulta corregida uniendo COLUMNS y TABLES para filtrar por TABLE_TYPE
        $tables = DB::select("
            SELECT c.TABLE_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS c
            JOIN INFORMATION_SCHEMA.TABLES t ON c.TABLE_NAME = t.TABLE_NAME AND c.TABLE_SCHEMA = t.TABLE_SCHEMA
            WHERE c.COLUMN_NAME = 'id' 
            AND c.TABLE_NAME != 'sessions'
            AND c.TABLE_SCHEMA = DATABASE()
            AND c.EXTRA NOT LIKE '%auto_increment%'
            AND t.TABLE_TYPE = 'BASE TABLE'
        ");

        if (empty($tables)) {
            return;
        }

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            
            try {
                // Aplicar el fix de auto-incremento asegurando que sea BIGINT UNSIGNED como es estándar en Laravel
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            } catch (\Exception $e) {
                Log::error("Error al aplicar AUTO_INCREMENT en {$tableName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos auto-incremento
    }
};
