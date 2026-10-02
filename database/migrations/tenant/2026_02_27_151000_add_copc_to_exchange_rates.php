<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ExchangeRate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Asegurar que id tenga auto_increment (bug detectado en entorno local)
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `exchange_rates` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');
        }

        ExchangeRate::updateOrCreate(
            ['currency_code' => 'COPC'],
            [
                'rate' => 0,
                'source' => 'manual'
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ExchangeRate::where('currency_code', 'COPC')->delete();
    }
};
