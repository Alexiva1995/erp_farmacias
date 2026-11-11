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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'purchase_unit_name')) {
                    $table->string('purchase_unit_name', 50)->nullable()->after('presentation');
                }
                if (!Schema::hasColumn('products', 'pack_unit_name')) {
                    $table->string('pack_unit_name', 50)->nullable()->after('purchase_unit_name');
                }
                if (!Schema::hasColumn('products', 'purchase_cost')) {
                    $table->decimal('purchase_cost', 15, 4)->nullable()->after('unit_cost');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('products', 'purchase_unit_name')) {
                    $columns[] = 'purchase_unit_name';
                }
                if (Schema::hasColumn('products', 'pack_unit_name')) {
                    $columns[] = 'pack_unit_name';
                }
                if (Schema::hasColumn('products', 'purchase_cost')) {
                    $columns[] = 'purchase_cost';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
