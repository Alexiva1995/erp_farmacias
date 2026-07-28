<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Corregir movimientos de inventario que tienen un ExpiredLog correspondiente y fueron guardados como 'loss'
        $expiredLogs = DB::table('expired_logs')->get();

        foreach ($expiredLogs as $log) {
            DB::table('inventory_movements')
                ->where('product_id', $log->product_id)
                ->where('movement_type', 'loss')
                ->whereDate('created_at', '>=', \Carbon\Carbon::parse($log->created_at)->subDay()->toDateString())
                ->whereDate('created_at', '<=', \Carbon\Carbon::parse($log->created_at)->addDay()->toDateString())
                ->update(['movement_type' => 'expired']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir
    }
};
