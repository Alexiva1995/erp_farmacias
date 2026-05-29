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
        Schema::create('expirations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_cycle_id')->constrained('inventory_cycles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_lot_id')->constrained('product_lots')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->integer('quantity')->comment('Unidades vencidas');
            $table->decimal('unit_cost', 10, 2)->comment('Costo unitario');
            $table->date('expiration_date')->comment('Fecha real de vencimiento');
            
            $table->timestamps();

            // Índices según dump
            $table->index('product_id');
            $table->index('expiration_date', 'idx_expiration_date');
            $table->index('product_lot_id', 'idx_product_lot');
            $table->index(['inventory_cycle_id', 'product_id'], 'idx_cycle_product');
            $table->index('supplier_id', 'idx_expiration_supplier');
        });

        // Agregar la columna generada virtualmente de forma condicional según el driver
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE expirations 
                ADD COLUMN total_cost DECIMAL(10,2) 
                GENERATED ALWAYS AS (quantity * unit_cost) VIRTUAL 
                COMMENT 'Costo total calculado'
                AFTER unit_cost
            ");
        } else {
            Schema::table('expirations', function (Blueprint $table) {
                $table->decimal('total_cost', 10, 2)->virtualAs('quantity * unit_cost')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expirations');
    }
};
