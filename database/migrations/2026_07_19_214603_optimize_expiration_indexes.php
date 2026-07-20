<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade índice en expired_logs.created_at para optimizar consultas de resumen mensual.
     * Elimina el índice duplicado product_lots_product_id_index (ya cubierto por idx_lot_product_exp).
     */
    public function up(): void
    {
        // Añadir índice en created_at de expired_logs solo si no existe aún
        $expiredLogIndexExists = collect(DB::select("SHOW INDEX FROM `expired_logs` WHERE Key_name = 'idx_expired_logs_created_at'"))->isNotEmpty();

        if (!$expiredLogIndexExists) {
            Schema::table('expired_logs', function (Blueprint $table) {
                $table->index('created_at', 'idx_expired_logs_created_at');
            });
        }

        // Eliminar índice duplicado en product_lots solo si existe en esta base de datos
        $productLotIndexExists = collect(DB::select("SHOW INDEX FROM `product_lots` WHERE Key_name = 'product_lots_product_id_index'"))->isNotEmpty();

        if ($productLotIndexExists) {
            Schema::table('product_lots', function (Blueprint $table) {
                $table->dropIndex('product_lots_product_id_index');
            });
        }
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

