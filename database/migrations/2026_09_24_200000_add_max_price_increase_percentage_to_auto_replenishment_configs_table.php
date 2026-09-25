<?php

declare(strict_types=1);

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
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->decimal('max_price_increase_percentage', 8, 2)
                ->nullable()
                ->after('con_descuento')
                ->comment('Porcentaje máximo permitido de sobrecosto respecto al costo base del producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->dropColumn('max_price_increase_percentage');
        });
    }
};
