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
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('inventory_movements', function (Blueprint $table) {
                try {
                    $table->dropForeign(['product_count_id']);
                } catch (\Throwable $e) {
                    // Prevenir error si la clave foránea no existe
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('product_count_id', 'inventory_movements_product_count_id_foreign')
                ->references('id')
                ->on('product_counts')
                ->nullOnDelete();
        });
    }
};
