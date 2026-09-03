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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN movement_type VARCHAR(50) NOT NULL");
        }

        // Actualizar todos los movimientos existentes con cantidad 0 o sin discrepancia
        DB::table('inventory_movements')
            ->whereIn('movement_type', ['adjustment', 'loss'])
            ->where(function ($query) {
                $query->where('quantity', 0)
                    ->orWhereRaw('stock_before = stock_after')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('product_counts')
                            ->whereColumn('product_counts.id', 'inventory_movements.product_count_id')
                            ->where('product_counts.discrepancy', 0);
                    });
            })
            ->update(['movement_type' => 'verification']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_movements')
            ->where('movement_type', 'verification')
            ->update(['movement_type' => 'adjustment']);
    }
};
