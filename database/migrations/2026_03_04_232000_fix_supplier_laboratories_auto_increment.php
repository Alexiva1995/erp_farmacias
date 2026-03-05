<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Forzar el auto-incremento y primary key si se perdieron
        // Primero verificamos si el id ya es primary key, si no, lo añadimos junto al auto_increment
        try {
            DB::statement("ALTER TABLE `supplier_laboratories` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
        } catch (\Exception $e) {
            // Si falla porque ya hay una PK, intentamos solo el auto_increment
            DB::statement("ALTER TABLE `supplier_laboratories` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        }
        
        try {
            DB::statement("ALTER TABLE `discount_rules` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
        } catch (\Exception $e) {
            DB::statement("ALTER TABLE `discount_rules` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos el auto-incremento
    }
};
