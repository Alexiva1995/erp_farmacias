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
        if (Schema::hasTable('product_distributions')) {
            Schema::table('product_distributions', function (Blueprint $table) {
                if (Schema::hasColumn('product_distributions', 'product_lot_id')) {
                    $table->unsignedBigInteger('product_lot_id')->nullable()->change();
                }

                if (!Schema::hasColumn('product_distributions', 'product_variant_id')) {
                    $table->foreignId('product_variant_id')
                        ->nullable()
                        ->after('product_lot_id')
                        ->constrained('product_variants')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_distributions')) {
            Schema::table('product_distributions', function (Blueprint $table) {
                if (Schema::hasColumn('product_distributions', 'product_variant_id')) {
                    $table->dropForeign(['product_variant_id']);
                    $table->dropColumn('product_variant_id');
                }
            });
        }
    }
};
