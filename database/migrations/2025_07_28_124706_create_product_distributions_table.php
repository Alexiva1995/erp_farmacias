<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // 1. Crear la nueva tabla para la relación uno-a-muchos
        Schema::create('product_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_count_id')->constrained('product_counts')->onDelete('cascade');
            $table->foreignId('product_lot_id')->constrained('product_lots')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });

        $hasIndex = false;
        try {
            if (Schema::hasTable('product_counts')) {
                $indexes = Schema::getIndexes('product_counts');
                foreach ($indexes as $index) {
                    if ($index['name'] === 'product_counts_product_lot_id_index') {
                        $hasIndex = true;
                    }
                }
            }
        } catch (\Exception $e) {
            $hasIndex = DB::getDriverName() === 'sqlite';
        }

        Schema::disableForeignKeyConstraints();
        Schema::table('product_counts', function (Blueprint $table) {
            $table->dropIndex('product_counts_product_lot_id_index');
            $table->dropConstrainedForeignId('product_lot_id');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // 1. Volver a agregar la columna a 'product_counts' (revertir el paso 2 de 'up')
        // Es importante recrearla exactamente como estaba.
        Schema::table('product_counts', function (Blueprint $table) {
            $table->foreignId('product_lot_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_lots')
                ->nullOnDelete();
        });

        Schema::dropIfExists('product_distributions');
    }
};
