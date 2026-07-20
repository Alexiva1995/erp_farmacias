<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade índice en expired_logs.created_at para optimizar consultas de resumen mensual.
     * Elimina el índice duplicado product_lots_product_id_index (ya cubierto por idx_lot_product_exp).
     */
    public function up(): void
    {
        // Índice en created_at de expired_logs — usado en WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        Schema::table('expired_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_expired_logs_created_at');
        });

        // Eliminar índice duplicado en product_lots (product_id ya está cubierto por idx_lot_product_exp)
        Schema::table('product_lots', function (Blueprint $table) {
            $table->dropIndex('product_lots_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('expired_logs', function (Blueprint $table) {
            $table->dropIndex('idx_expired_logs_created_at');
        });

        Schema::table('product_lots', function (Blueprint $table) {
            $table->index('product_id', 'product_lots_product_id_index');
        });
    }
};

