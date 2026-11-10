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
        if (Schema::hasTable('modifier_groups') && !Schema::hasColumn('modifier_groups', 'base_product_id')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->foreignId('base_product_id')
                    ->nullable()
                    ->after('description')
                    ->comment('Insumo base de la receta que este grupo reemplaza (ej. Helado General)')
                    ->constrained('products')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('order_detail_modifiers') && !Schema::hasColumn('order_detail_modifiers', 'base_product_id')) {
            Schema::table('order_detail_modifiers', function (Blueprint $table) {
                $table->foreignId('base_product_id')
                    ->nullable()
                    ->after('product_id')
                    ->comment('Insumo base que fue sustituido por este modificador')
                    ->constrained('products')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_detail_modifiers') && Schema::hasColumn('order_detail_modifiers', 'base_product_id')) {
            Schema::table('order_detail_modifiers', function (Blueprint $table) {
                $table->dropForeign(['base_product_id']);
                $table->dropColumn('base_product_id');
            });
        }

        if (Schema::hasTable('modifier_groups') && Schema::hasColumn('modifier_groups', 'base_product_id')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->dropForeign(['base_product_id']);
                $table->dropColumn('base_product_id');
            });
        }
    }
};
