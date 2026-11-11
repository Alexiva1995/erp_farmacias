<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones para agregar el tipo de categoría (inventario, plato o ambos).
     */
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            return;
        }

        if (!Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('type', 20)->default('inventory')->after('name')->index();
            });
        }

        // Reclasificar automáticamente categorías existentes según su uso en platos y productos
        if (Schema::hasTable('dishes') && Schema::hasTable('products')) {
            $dishCategoryIds = DB::table('dishes')
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $productCategoryIds = DB::table('products')
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $onlyDishIds = array_values(array_diff($dishCategoryIds, $productCategoryIds));
            $bothIds = array_values(array_intersect($dishCategoryIds, $productCategoryIds));

            if (!empty($onlyDishIds)) {
                DB::table('categories')
                    ->whereIn('id', $onlyDishIds)
                    ->update(['type' => 'dish']);
            }

            if (!empty($bothIds)) {
                DB::table('categories')
                    ->whereIn('id', $bothIds)
                    ->update(['type' => 'both']);
            }
        }
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};
